<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pembelian_gudang_detail', function (Blueprint $table) {
            $table->foreignId('harga_acuan_id')
                ->nullable()
                ->after('harga_invoice')
                ->constrained('harga_acuan_origami')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pembelian_gudang_detail', function (Blueprint $table) {
            $table->dropForeign(['harga_acuan_id']);
            $table->dropColumn('harga_acuan_id');
        });
    }
};