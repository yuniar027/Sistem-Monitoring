<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alokasi_etalase', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('sku');
            $table->enum('channel', ['shopee', 'tiktok']);
            $table->string('nama_toko');
            $table->integer('kuantitas_dialokasikan');
            $table->integer('kuantitas_terjual')->default(0);
            $table->integer('kuantitas_sisa');
            $table->date('tanggal_alokasi');
            $table->enum('status', ['aktif', 'ditarik']);
            $table->timestamps();

            $table->foreign('sku')->references('sku')->on('produk_master')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alokasi_etalase');
    }
};
