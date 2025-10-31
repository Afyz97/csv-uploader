<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Upload extends Model
{
    protected $fillable = [
        'original_name',
        'stored_path',
        'mime',
        'size_bytes',
        'checksum_sha256',
        'status',
        'rows_total',
        'rows_upserted',
        'rows_failed',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}
