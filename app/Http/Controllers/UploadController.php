<?php

namespace App\Http\Controllers;

use App\Http\Resources\UploadResource;
use App\Jobs\ProcessUploadJob;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function index()
    {
        $uploads = Upload::latest()->take(50)->get();
        return view('uploads.index', compact('uploads'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'csv' => [
                'required',
                'file',
                'mimes:csv,txt',
                'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel',
                'max:2097152' // 2GB in KB; adjust to your needs
            ],
        ]);

        $file = $request->file('csv');

        // Stream checksum (no large memory usage)
        $checksum = hash_file('sha256', $file->getRealPath());

        // Sanitize original filename (Windows-safe)
        $orig = $file->getClientOriginalName();
        $cleanOrig = preg_replace('/[^\w\-. ]+/u', '_', $orig);

        // Save using Storage (local disk)
        $storedPath = Storage::disk('local')->putFileAs(
            'uploads',
            $file,
            now()->format('Ymd_His') . '_' . $cleanOrig
        );

        try {
            $upload = Upload::create([
                'original_name'   => $orig,
                'stored_path'     => $storedPath,
                'mime'            => $file->getClientMimeType(),
                'size_bytes'      => $file->getSize(),
                'checksum_sha256' => $checksum,
                'status'          => 'queued',
            ]);
        } catch (\Throwable $e) {
            // Duplicate checksum + same name: record as skipped (idempotent history)
            // still store the file (different name) so user sees attempt
            $altStoredPath = Storage::disk('local')->putFileAs(
                'uploads',
                $file,
                now()->format('Ymd_His') . '_dup_' . $cleanOrig
            );

            $upload = Upload::create([
                'original_name'   => $orig,
                'stored_path'     => $altStoredPath,
                'mime'            => $file->getClientMimeType(),
                'size_bytes'      => $file->getSize(),
                'checksum_sha256' => $checksum,
                'status'          => 'skipped',
                'meta'            => ['reason' => 'Duplicate of previously uploaded file; processing skipped'],
            ]);

            return redirect()->route('uploads.index')
                ->with('message', 'Duplicate file detected — upload recorded but processing skipped.');
        }

        // Dispatch background job
        ProcessUploadJob::dispatch($upload);

        return redirect()->route('uploads.index')
            ->with('message', 'File queued for processing.');
    }

    // JSON endpoint for polling
    public function poll()
    {
        $uploads = Upload::latest()->take(50)->get();
        return UploadResource::collection($uploads);
    }
}
