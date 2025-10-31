<?php

namespace App\Http\Controllers;

use App\Http\Resources\UploadResource;
use App\Jobs\ProcessUploadJob;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    /**
     * Show upload form + recent uploads list.
     */
    public function index()
    {
        $uploads = Upload::latest()->take(50)->get();
        return view('uploads.index', compact('uploads'));
    }

    /**
     * Accept a CSV upload and enqueue background processing.
     * Allows multiple uploads with the SAME original filename without conflicts.
     */
    public function store(Request $request)
    {
        $request->validate([
            'csv' => [
                'required',
                'file',
                // Accept common CSV mimetypes across browsers
                'mimes:csv,txt',
                'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel',
                // Value is in kilobytes; 2 GB shown as example (adjust to your policy)
                'max:2097152',
            ],
        ]);

        $file = $request->file('csv');

        // Streamed checksum (no large memory spike)
        $checksum = hash_file('sha256', $file->getRealPath());

        // Sanitize original filename for Windows safety
        $originalName = $file->getClientOriginalName();
        $cleanName    = preg_replace('/[^\w\-. ]+/u', '_', $originalName);

        // Persist the file on the 'local' disk; prefix with timestamp to avoid collisions
        $storedPath = Storage::disk('local')->putFileAs(
            'uploads',
            $file,
            now()->format('Ymd_His') . '_' . $cleanName
        );

        // Always create a history record (no unique constraint, no try/catch)
        $upload = Upload::create([
            'original_name'   => $originalName,
            'stored_path'     => $storedPath,
            'mime'            => $file->getClientMimeType(),
            'size_bytes'      => $file->getSize(),
            'checksum_sha256' => $checksum,
            'status'          => 'queued',
            // Optional: mark duplicates silently (same checksum as a prior upload)
            // 'meta'            => optional(
            //     Upload::where('checksum_sha256', $checksum)->oldest()->first()
            // )->only('id') ? ['duplicate_of' => Upload::where('checksum_sha256', $checksum)->oldest()->first()->id] : null,
        ]);

        // Queue the background job (products are idempotent via UPSERT on unique_key)
        ProcessUploadJob::dispatch($upload);

        return redirect()
            ->route('uploads.index')
            ->with('message', 'File queued for processing.');
    }

    /**
     * JSON for polling the latest uploads (uses a Transformer).
     */
    public function poll()
    {
        $uploads = Upload::latest()->take(50)->get();
        return UploadResource::collection($uploads);
    }
}
