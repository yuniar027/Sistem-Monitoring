<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_process_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_process_id')
                ->constrained('production_processes');
            $table->foreignId('variasi_gudang_id')
                ->constrained('stok_variasi_gudang');
            $table->decimal('multiplier', 10, 2);
            $table->timestamps();

            $table->unique(['production_process_id', 'variasi_gudang_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_process_targets');
    }
};
