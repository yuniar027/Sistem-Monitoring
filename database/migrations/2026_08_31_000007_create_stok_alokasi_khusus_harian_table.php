<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_alokasi_khusus_harian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_gudang_id')
                ->constrained('stok_barang_gudang')
                ->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('kode_alokasi'); // contoh: K 3 SET, K 18, K 33, K 39/30, K 12, K 27, K 48
            $table->decimal('kuantitas', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['barang_gudang_id', 'tanggal', 'kode_alokasi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_alokasi_khusus_harian');
    }
};
