<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembelian_gudang_detail', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pembelian_gudang_id')
                ->constrained('pembelian_gudang')
                ->cascadeOnDelete();

            $table->foreignId('barang_gudang_id')
                ->constrained('stok_barang_gudang')
                ->restrictOnDelete();

            $table->decimal('kuantitas', 12, 2);

            // Harga yang benar-benar tercantum pada invoice pabrik.
            $table->decimal('harga_invoice', 15, 2);

            // Snapshot harga acuan ketika invoice dicatat.
            // Tidak berubah meskipun harga acuan master berubah di kemudian hari.
            $table->decimal('harga_acuan_snapshot', 15, 2)->nullable();

            $table->decimal('nilai_acuan', 15, 2)->nullable();
            $table->decimal('nilai_invoice', 15, 2);

            $table->decimal('selisih_nominal', 15, 2)->nullable();
            $table->decimal('persen_selisih', 8, 2)->nullable();

            // naik | turun | tetap | baru
            $table->string('kategori_perbandingan')->nullable();

            // tidak_perlu | pending | approved | rejected
            $table->string('status_approval')->default('tidak_perlu');

            $table->text('catatan')->nullable();

            $table->timestamps();

            $table->index(['barang_gudang_id', 'status_approval']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembelian_gudang_detail');
    }
};