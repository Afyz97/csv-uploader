<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            // remove UNIQUE(checksum_sha256, original_name)
            $table->dropUnique('uq_uploads_checksum_name'); // name used in your original migration
            // optional: keep helpful indexes (checksum already indexed in original)
            // $table->index('original_name');
        });
    }

    public function down(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->unique(['checksum_sha256', 'original_name'], 'uq_uploads_checksum_name');
        });
    }
};
