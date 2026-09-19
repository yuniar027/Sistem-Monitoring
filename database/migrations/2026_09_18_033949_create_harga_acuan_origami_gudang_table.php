<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harga_acuan_origami', function (Blueprint $table) {
            $table->id();

            $table->foreignId('barang_gudang_id')
                ->constrained('stok_barang_gudang')
                ->restrictOnDelete();

            $table->decimal('harga_acuan', 15, 2);

            $table->date('berlaku_mulai');
            $table->date('berlaku_sampai')->nullable();

            $table->boolean('is_active')->default(true);

            $table->string('catatan')->nullable();

            $table->timestamps();

            $table->index(['barang_gudang_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harga_acuan_origami');
    }
};