<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pemetaan_manual_bahan_baku', function (Blueprint $table) {
            $table->id();
            $table->string('nama_item')->unique();
            $table->foreignId('bahan_baku_id')->constrained('bahan_baku');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemetaan_manual_bahan_baku');
    }
};