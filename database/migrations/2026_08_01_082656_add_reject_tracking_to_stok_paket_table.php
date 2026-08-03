<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stok_paket', function (Blueprint $table) {
            $table->integer('jumlah_target')->nullable()->after('jumlah_paket');
            $table->integer('jumlah_reject')->default(0)->after('jumlah_target');
            $table->decimal('persentase_reject', 5, 2)->default(0)->after('jumlah_reject');
            $table->string('status_reject')->default('normal')->after('persentase_reject');
        });
    }

    public function down(): void
    {
        Schema::table('stok_paket', function (Blueprint $table) {
            $table->dropColumn(['jumlah_target', 'jumlah_reject', 'persentase_reject', 'status_reject']);
        });
    }
};