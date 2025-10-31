<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\Upload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Upload $upload;

    public function __construct(Upload $upload)
    {
        $this->upload = $upload;
        $this->onQueue('uploads');
    }

    public function handle(): void
    {
        $this->upload->refresh();
        if (!in_array($this->upload->status, ['queued', 'processing'])) {
            return;
        }

        $this->upload->update(['status' => 'processing']);

        $disk = Storage::disk('local');
        $path = $this->upload->stored_path;

        if (!$disk->exists($path)) {
            $this->upload->update([
                'status' => 'failed',
                'meta'   => ['error' => "File not found at disk('local'): {$path}"],
            ]);
            return;
        }

        $handle = $disk->readStream($path);
        if (!$handle) {
            $this->upload->update([
                'status' => 'failed',
                'meta'   => ['error' => 'Unable to open file stream'],
            ]);
            return;
        }

        // Auto-detect delimiter
        $firstLine = fgets($handle, 4096) ?: '';
        rewind($handle);
        $delims = ["," => substr_count($firstLine, ","), ";" => substr_count($firstLine, ";"), "\t" => substr_count($firstLine, "\t")];
        $delimiter = array_key_first(array_filter($delims, fn($c) => $c === max($delims))) ?? ",";

        $header = fgetcsv($handle, 0, $delimiter);
        if (!$header) {
            fclose($handle);
            $this->upload->update([
                'status' => 'failed',
                'meta'   => ['error' => 'Empty file or invalid CSV'],
            ]);
            return;
        }

        // Remove BOM if present
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }

        // Normalize headers
        $normalized = array_map(fn($h) => strtoupper(trim(preg_replace('/\s+/', ' ', $h ?? ''))), $header);

        $find = function (array $aliases) use ($normalized): ?int {
            foreach ($aliases as $a) {
                $pos = array_search($a, $normalized, true);
                if ($pos !== false) return $pos;
            }
            return null;
        };

        $idx = [
            'unique_key'             => $find(['UNIQUE_KEY']),
            'product_title'          => $find(['PRODUCT_TITLE', 'TITLE']),
            'product_description'    => $find(['PRODUCT_DESCRIPTION', 'DESCRIPTION', 'DESC']),
            'style_no'               => $find(['STYLE#', 'STYLE #', 'STYLE']),
            'sanmar_mainframe_color' => $find(['SANMAR_MAINFRAME_COLOR', 'MAINFRAME_COLOR', 'SANMAR_COLOR']),
            'size'                   => $find(['SIZE']),
            'color_name'             => $find(['COLOR_NAME', 'COLOR']),
            'piece_price'            => $find(['PIECE_PRICE', 'PRICE']),
        ];

        if ($idx['unique_key'] === null) {
            fclose($handle);
            $this->upload->update([
                'status' => 'failed',
                'meta'   => ['error' => 'CSV missing UNIQUE_KEY column'],
            ]);
            return;
        }

        $rowsTotal = 0;
        $rowsUpserted = 0;
        $rowsFailed = 0;
        $errors = [];

        $clean = function (?string $val): ?string {
            if ($val === null) return null;
            $enc = mb_detect_encoding($val, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true) ?: 'UTF-8';
            $v = mb_convert_encoding($val, 'UTF-8', $enc);

            $v = preg_replace('/[^\P{C}\t\n\r]/u', '', $v);
            return trim($v);
        };

        $batch = [];
        $batchSize = 100;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowsTotal++;

            $record = [
                'unique_key'             => $clean($row[$idx['unique_key']] ?? null),
                'product_title'          => $clean($row[$idx['product_title']] ?? null),
                'product_description'    => $clean($row[$idx['product_description']] ?? null),
                'style_no'               => $clean($row[$idx['style_no']] ?? null),
                'sanmar_mainframe_color' => $clean($row[$idx['sanmar_mainframe_color']] ?? null),
                'size'                   => $clean($row[$idx['size']] ?? null),
                'color_name'             => $clean($row[$idx['color_name']] ?? null),
                'piece_price'            => null,
                'updated_at'             => now(),
                'created_at'             => now(),
            ];

            // price normalization
            $rawPrice = $row[$idx['piece_price']] ?? null;
            if ($rawPrice !== null && $rawPrice !== '') {
                $norm = preg_replace('/[^\d\.\-]/', '', (string)$rawPrice);
                $record['piece_price'] = is_numeric($norm) ? number_format((float)$norm, 2, '.', '') : null;
            }

            if (!$record['unique_key']) {
                $rowsFailed++;
                $errors[] = ['row' => $rowsTotal, 'error' => 'Missing UNIQUE_KEY'];
                continue;
            }

            $batch[] = $record;

            if (count($batch) >= $batchSize) {
                $rowsUpserted += $this->upsertBatch($batch);
                $batch = [];
            }
        }
        fclose($handle);

        if ($batch) {
            $rowsUpserted += $this->upsertBatch($batch);
        }

        $this->upload->update([
            'status'         => 'completed',
            'rows_total'     => $rowsTotal,
            'rows_upserted'  => $rowsUpserted,
            'rows_failed'    => $rowsFailed,
            'meta'           => ['errors' => $errors, 'header_map' => $idx, 'delimiter' => $delimiter],
        ]);
    }

    protected function upsertBatch(array $batch): int
    {
        $updateCols = [
            'product_title',
            'product_description',
            'style_no',
            'sanmar_mainframe_color',
            'size',
            'color_name',
            'piece_price',
            'updated_at'
        ];

        DB::table('products')->upsert($batch, ['unique_key'], $updateCols);
        return count($batch);
    }

    public function failed(Throwable $e): void
    {
        $this->upload->update([
            'status' => 'failed',
            'meta'   => array_merge($this->upload->meta ?? [], ['error' => $e->getMessage()]),
        ]);
    }
}
