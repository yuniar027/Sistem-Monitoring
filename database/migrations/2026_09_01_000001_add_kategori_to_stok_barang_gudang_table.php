<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stok_barang_gudang', function (Blueprint $table) {
            // default 'awan' supaya ~200 data yang sudah keimport (semuanya
            // masih Awan) otomatis ke-set benar tanpa perlu backfill manual
            $table->string('kategori')->default('awan')->after('nama_barang');
        });
    }

    public function down(): void
    {
        Schema::table('stok_barang_gudang', function (Blueprint $table) {
            $table->dropColumn('kategori');
        });
    }
};
