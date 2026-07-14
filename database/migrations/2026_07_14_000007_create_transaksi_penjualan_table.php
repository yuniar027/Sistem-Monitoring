<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi_penjualan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->enum('channel', ['shopee', 'tiktok']);
            $table->string('no_pesanan')->unique();
            $table->string('no_resi')->nullable();
            $table->string('sku');
            $table->integer('jumlah');
            $table->decimal('harga', 15, 2);
            $table->decimal('total', 15, 2);
            $table->date('tanggal');
            $table->string('status_order');
            $table->timestamps();

            $table->foreign('sku')->references('sku')->on('produk_master')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_penjualan');
    }
};
