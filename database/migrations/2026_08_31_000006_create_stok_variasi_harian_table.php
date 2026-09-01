<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_variasi_harian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variasi_gudang_id')
                ->constrained('stok_variasi_gudang')
                ->cascadeOnDelete();
            $table->date('tanggal');
            $table->decimal('stok_awal', 10, 2)->default(0); // otomatis = sisa tanggal sebelumnya
            $table->decimal('input', 10, 2)->default(0); // diisi manual (hasil pecahan dari barang induk)
            $table->decimal('out', 10, 2)->default(0); // diisi manual (stok keluar/terjual)
            $table->timestamps();

            // satu variasi cuma boleh punya satu snapshot per tanggal
            $table->unique(['variasi_gudang_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_variasi_harian');
    }
};
