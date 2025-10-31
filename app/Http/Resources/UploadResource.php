<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UploadResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'         => $this->id,
            'file'       => $this->original_name,
            'status'     => $this->status,
            'size_bytes' => $this->size_bytes,
            'rows'       => [
                'total'    => $this->rows_total,
                'upserted' => $this->rows_upserted,
                'failed'   => $this->rows_failed,
            ],
            'checksum'   => $this->checksum_sha256,
            'meta'       => $this->meta,
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}
