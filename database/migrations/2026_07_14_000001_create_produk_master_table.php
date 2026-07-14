<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produk_master', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('sku')->unique()->index();
            $table->string('nama_produk');
            $table->string('satuan_jual');
            $table->string('satuan_beli');
            $table->integer('isi_per_satuan_beli')->default(1);
            $table->string('kategori')->nullable();
            $table->decimal('harga_modal_default', 15, 2)->nullable();
            $table->integer('target_stok_minimum')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produk_master');
    }
};
