<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penjualan_gelondongan', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->string('keterangan')->nullable();
            $table->string('channel')->nullable();
            $table->decimal('nominal_penjualan', 15, 2);
            $table->decimal('nominal_hpp', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penjualan_gelondongan');
    }
};
