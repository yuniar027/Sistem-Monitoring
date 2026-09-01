<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stok_barang_gudang', function (Blueprint $table) {
            $table->dropColumn(['rak', 'input']);
        });
    }

    public function down(): void
    {
        Schema::table('stok_barang_gudang', function (Blueprint $table) {
            $table->decimal('rak', 10, 2)->default(0);
            $table->decimal('input', 10, 2)->default(0);
        });
    }
};
