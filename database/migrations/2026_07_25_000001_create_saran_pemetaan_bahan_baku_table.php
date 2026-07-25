<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saran_pemetaan_bahan_baku', function (Blueprint $table) {
            $table->id();
            $table->string('nama_item');
            $table->string('kode_bahan_disarankan')->nullable();
            $table->string('nama_bahan')->nullable();
            $table->string('metode');
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saran_pemetaan_bahan_baku');
    }
};
