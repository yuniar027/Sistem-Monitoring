<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bahan_baku_masuk', function (Blueprint $table) {
            $table->string('status_pembayaran')->default('belum_lunas');
        });

        Schema::table('stok_masuk', function (Blueprint $table) {
            $table->string('status_pembayaran')->default('belum_lunas');
        });
    }

    public function down(): void
    {
        Schema::table('bahan_baku_masuk', function (Blueprint $table) {
            $table->dropColumn('status_pembayaran');
        });

        Schema::table('stok_masuk', function (Blueprint $table) {
            $table->dropColumn('status_pembayaran');
        });
    }
};