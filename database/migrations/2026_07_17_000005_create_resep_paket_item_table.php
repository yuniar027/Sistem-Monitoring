<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resep_paket_item', function (Blueprint $table) {
            $table->id();
            $table->string('sku');
            $table->unsignedBigInteger('bahan_baku_id');
            $table->integer('kuantitas_per_paket');
            $table->timestamps();

            // Foreign keys
            $table->foreign('sku')->references('sku')->on('produk_master')->restrictOnDelete();
            $table->foreign('bahan_baku_id')->references('id')->on('bahan_baku')->restrictOnDelete();

            // Unique constraint on sku + bahan_baku_id combination
            $table->unique(['sku', 'bahan_baku_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resep_paket_item');
    }
};
