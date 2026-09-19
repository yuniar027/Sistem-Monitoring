<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembelian_gudang', function (Blueprint $table) {
            $table->id();

            $table->string('nomor_invoice')->unique();
            $table->date('tanggal');
            $table->string('supplier')->nullable();

            $table->text('catatan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembelian_gudang');
    }
};