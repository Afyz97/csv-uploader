<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('stored_path'); // Storage::disk('local') path, e.g. uploads/20251101_203000_file.csv
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum_sha256', 64)->index();
            $table->enum('status', ['queued', 'processing', 'completed', 'failed', 'skipped'])->default('queued');
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_upserted')->default(0);
            $table->unsignedInteger('rows_failed')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['checksum_sha256', 'original_name'], 'uq_uploads_checksum_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploads');
    }
};
