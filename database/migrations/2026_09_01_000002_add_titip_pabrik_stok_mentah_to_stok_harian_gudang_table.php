<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stok_harian_gudang', function (Blueprint $table) {
            $table->decimal('um_titip_pabrik', 10, 2)->nullable()->after('input');
            $table->decimal('stok_mentah_umma', 10, 2)->nullable()->after('um_titip_pabrik');
        });
    }

    public function down(): void
    {
        Schema::table('stok_harian_gudang', function (Blueprint $table) {
            $table->dropColumn(['um_titip_pabrik', 'stok_mentah_umma']);
        });
    }
};
