<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stok_variasi_harian', function (Blueprint $table) {
            // Terpisah dari `input` (manual) supaya sinkronisasi otomatis dari
            // ProductionEvent tidak pernah menimpa/numpuk dengan input manual.
            // stok_hasil = stok_awal + input (manual) + produksi_input (auto).
            $table->decimal('produksi_input', 10, 2)->default(0)->after('input');
        });
    }

    public function down(): void
    {
        Schema::table('stok_variasi_harian', function (Blueprint $table) {
            $table->dropColumn('produksi_input');
        });
    }
};