<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_barang_gudang', function (Blueprint $table) {
            $table->id();
            $table->string('kode_barang')->unique()->nullable();
            $table->string('nama_barang');
            $table->decimal('stok_aman', 10, 2)->default(0);
            $table->decimal('rak', 10, 2)->default(0);
            $table->decimal('input', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_barang_gudang');
    }
};
