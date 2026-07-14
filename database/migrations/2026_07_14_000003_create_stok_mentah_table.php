<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_mentah', function (Blueprint $table) {
            $table->string('sku')->primary();
            $table->integer('kuantitas_tersedia')->default(0);
            $table->timestamp('updated_at')->nullable();

            $table->foreign('sku')->references('sku')->on('produk_master')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_mentah');
    }
};
