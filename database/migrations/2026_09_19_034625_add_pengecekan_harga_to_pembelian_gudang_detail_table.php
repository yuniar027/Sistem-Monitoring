<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pembelian_gudang_detail', function (Blueprint $table) {
            $table->boolean('sudah_dicek')
                ->default(false)
                ->after('status_approval');

            $table->timestamp('dicek_pada')
                ->nullable()
                ->after('sudah_dicek');
        });
    }

    public function down(): void
    {
        Schema::table('pembelian_gudang_detail', function (Blueprint $table) {
            $table->dropColumn(['sudah_dicek', 'dicek_pada']);
        });
    }
};