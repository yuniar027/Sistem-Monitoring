<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('perbandingan_harga_stok_masuk');
        Schema::dropIfExists('harga_acuan_origami');
    }

    public function down(): void
    {
        // Migration lama sudah tidak digunakan.
        // Tidak membuat ulang tabel marketplace finance.
    }
};