<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_masuk', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('tanggal');
            $table->string('sku');
            $table->string('vendor');
            $table->integer('kuantitas');
            $table->decimal('harga_satuan', 15, 2);
            $table->decimal('biaya_kirim', 15, 2)->default(0);
            $table->decimal('total_nominal', 15, 2);
            $table->timestamps();

            $table->foreign('sku')->references('sku')->on('produk_master')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_masuk');
    }
};
