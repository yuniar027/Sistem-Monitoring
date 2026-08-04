<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tugas_packing', function (Blueprint $table) {
            $table->boolean('dari_urutan_kedatangan')->default(false)->after('tanggal_dibuat');
        });
    }

    public function down(): void
    {
        Schema::table('tugas_packing', function (Blueprint $table) {
            $table->dropColumn('dari_urutan_kedatangan');
        });
    }
};