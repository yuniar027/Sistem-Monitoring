<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perbandingan_harga_stok_masuk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stok_masuk_id')->constrained('stok_masuk')->cascadeOnDelete();
            $table->string('sku')->index();

            // Snapshot: acuan mana yang aktif saat transaksi ini dicatat.
            // Nullable karena SKU bisa belum punya Harga Acuan sama sekali.
            $table->foreignId('harga_acuan_origami_id')->nullable()
                ->constrained('harga_acuan_origami')->nullOnDelete();

            $table->decimal('harga_acuan', 15, 2)->nullable();
            $table->decimal('harga_invoice', 15, 2);
            $table->integer('kuantitas');
            $table->decimal('nilai_acuan', 15, 2)->nullable();
            $table->decimal('nilai_invoice', 15, 2);
            $table->decimal('selisih_nominal', 15, 2)->nullable();
            $table->decimal('persen_selisih', 8, 2)->nullable();

            // naik | turun | tetap | baru (belum ada Harga Acuan sebelumnya)
            $table->string('kategori');

            // tidak_perlu (kategori tetap) | pending | approved | rejected
            $table->string('status_approval')->default('pending');

            $table->string('catatan')->nullable();
            $table->timestamp('diproses_pada')->nullable();
            $table->timestamps();

            $table->foreign('sku')->references('sku')->on('produk_master')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perbandingan_harga_stok_masuk');
    }
};
