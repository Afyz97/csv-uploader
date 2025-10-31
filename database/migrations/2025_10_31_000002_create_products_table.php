<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('unique_key')->unique(); // UNIQUE_KEY
            $table->string('product_title')->nullable();
            $table->text('product_description')->nullable();
            $table->string('style_no')->nullable(); // STYLE#
            $table->string('sanmar_mainframe_color')->nullable();
            $table->string('size')->nullable();
            $table->string('color_name')->nullable();
            $table->decimal('piece_price', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
