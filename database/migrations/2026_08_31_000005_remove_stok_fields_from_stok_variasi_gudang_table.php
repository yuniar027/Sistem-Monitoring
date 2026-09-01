<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stok_variasi_gudang', function (Blueprint $table) {
            $table->dropColumn(['stok_awal', 'input', 'out']);
        });
    }

    public function down(): void
    {
        Schema::table('stok_variasi_gudang', function (Blueprint $table) {
            $table->decimal('stok_awal', 10, 2)->default(0);
            $table->decimal('input', 10, 2)->default(0);
            $table->decimal('out', 10, 2)->default(0);
        });
    }
};
