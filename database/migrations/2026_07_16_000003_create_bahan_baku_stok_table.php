<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bahan_baku_stok', function (Blueprint $table) {
            $table->foreignId('bahan_baku_id')->primary()->constrained('bahan_baku')->restrictOnDelete();
            $table->integer('kuantitas_tersedia')->default(0);
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bahan_baku_stok');
    }
};
