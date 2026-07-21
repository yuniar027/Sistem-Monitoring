<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produk_master', function (Blueprint $table) {
            $table->decimal('harga_jual_referensi', 15, 2)->nullable()->after('harga_modal_default');
        });
    }

    public function down(): void
    {
        Schema::table('produk_master', function (Blueprint $table) {
            $table->dropColumn('harga_jual_referensi');
        });
    }
};
