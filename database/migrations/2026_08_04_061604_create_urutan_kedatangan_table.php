<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urutan_kedatangan', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->foreignId('packer_id')->constrained('packers');
            $table->integer('urutan');
            $table->timestamps();

            $table->unique(['tanggal', 'packer_id']);
            $table->unique(['tanggal', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urutan_kedatangan');
    }
};