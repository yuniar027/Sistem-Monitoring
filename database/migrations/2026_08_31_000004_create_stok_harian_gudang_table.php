<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_harian_gudang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_gudang_id')
                ->constrained('stok_barang_gudang')
                ->cascadeOnDelete();
            $table->date('tanggal');
            $table->decimal('rak', 10, 2)->default(0); // otomatis = stok_akhir tanggal sebelumnya
            $table->decimal('input', 10, 2)->default(0); // diisi manual sepanjang hari
            $table->timestamps();

            // satu barang cuma boleh punya satu snapshot per tanggal
            $table->unique(['barang_gudang_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_harian_gudang');
    }
};
