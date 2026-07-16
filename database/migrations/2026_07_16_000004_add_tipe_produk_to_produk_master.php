<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produk_master', function (Blueprint $table) {
            $table->enum('tipe_produk', ['simple', 'rakitan'])->default('simple')->after('isi_per_satuan_beli');
        });
    }

    public function down(): void
    {
        Schema::table('produk_master', function (Blueprint $table) {
            $table->dropColumn('tipe_produk');
        });
    }
};
