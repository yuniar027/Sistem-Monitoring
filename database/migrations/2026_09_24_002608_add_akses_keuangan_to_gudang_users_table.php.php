<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gudang_users', function (Blueprint $table) {
            // Default false: semua akun gudang yang SUDAH ADA (termasuk
            // gudang@ummababyshop.com) otomatis kebatasi ke Monitoring
            // Stok Ringkas saja begitu migration ini jalan. Kalau ada
            // akun lain yang harus tetap akses penuh (mis. Mbak Via),
            // set manual jadi true lewat tinker setelah migrate.
            $table->boolean('akses_keuangan')->default(false)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('gudang_users', function (Blueprint $table) {
            $table->dropColumn('akses_keuangan');
        });
    }
};