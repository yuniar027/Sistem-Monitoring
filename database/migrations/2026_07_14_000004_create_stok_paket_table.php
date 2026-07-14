<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_paket', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('sku');
            $table->integer('kuantitas_per_paket');
            $table->integer('jumlah_paket');
            $table->date('tanggal_dibuat');
            $table->enum('status', ['belum_distribusi', 'sudah_distribusi']);
            $table->timestamps();

            $table->foreign('sku')->references('sku')->on('produk_master')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_paket');
    }
};
