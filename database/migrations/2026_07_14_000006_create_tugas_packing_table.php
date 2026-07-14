<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tugas_packing', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('sku');
            $table->enum('channel_tujuan', ['shopee', 'tiktok']);
            $table->integer('kuantitas');
            $table->enum('status', ['belum_dikerjakan', 'dikerjakan', 'selesai']);
            $table->unsignedBigInteger('ditugaskan_ke')->nullable();
            $table->date('tanggal_dibuat');
            $table->date('tanggal_selesai')->nullable();
            $table->timestamps();

            $table->foreign('sku')->references('sku')->on('produk_master')->cascadeOnDelete();
            $table->foreign('ditugaskan_ke')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tugas_packing');
    }
};
