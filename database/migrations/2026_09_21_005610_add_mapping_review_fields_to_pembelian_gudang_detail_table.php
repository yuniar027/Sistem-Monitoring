<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pembelian_gudang_detail', function (Blueprint $table) {
            $table->dropForeign(['barang_gudang_id']);
        });

        Schema::table('pembelian_gudang_detail', function (Blueprint $table) {
            $table->unsignedBigInteger('barang_gudang_id')
                ->nullable()
                ->change();

            $table->string('kode_barang_invoice')
                ->nullable()
                ->after('barang_gudang_id');

            $table->string('nama_barang_invoice')
                ->nullable()
                ->after('kode_barang_invoice');

            $table->string('status_pemetaan')
                ->default('cocok')
                ->after('nama_barang_invoice');
        });

        Schema::table('pembelian_gudang_detail', function (Blueprint $table) {
            $table->foreign('barang_gudang_id')
                ->references('id')
                ->on('stok_barang_gudang')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pembelian_gudang_detail', function (Blueprint $table) {
            $table->dropForeign(['barang_gudang_id']);
            $table->dropColumn([
                'kode_barang_invoice',
                'nama_barang_invoice',
                'status_pemetaan',
            ]);
        });

        Schema::table('pembelian_gudang_detail', function (Blueprint $table) {
            $table->unsignedBigInteger('barang_gudang_id')
                ->nullable(false)
                ->change();

            $table->foreign('barang_gudang_id')
                ->references('id')
                ->on('stok_barang_gudang')
                ->restrictOnDelete();
        });
    }
};