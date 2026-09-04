<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_variasi_gudang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_gudang_id')
                ->constrained('stok_barang_gudang')
                ->cascadeOnDelete();
            $table->string('kode_variasi'); // contoh: K3SET, K18, K33, K39/30, K12, K27, K48, 3S BTG, 12PCS
            $table->decimal('stok_aman', 10, 2)->default(0);
            $table->decimal('stok_awal', 10, 2)->default(0);
            $table->decimal('input', 10, 2)->default(0);
            $table->decimal('out', 10, 2)->default(0);
            $table->timestamps();

            // satu barang gudang tidak boleh punya kode_variasi ganda
            $table->unique(['barang_gudang_id', 'kode_variasi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_variasi_gudang');
    }
};
