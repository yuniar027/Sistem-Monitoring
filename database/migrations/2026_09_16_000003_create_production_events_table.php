<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_events', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->foreignId('barang_gudang_id')
                ->constrained('stok_barang_gudang');
            $table->foreignId('production_process_target_id')
                ->constrained('production_process_targets');
            $table->decimal('source_quantity', 10, 2);
            $table->decimal('multiplier_snapshot', 10, 2);
            $table->decimal('output_quantity', 10, 2);
            $table->timestamps();

            $table->index('tanggal');
            $table->index('barang_gudang_id');
            $table->index('production_process_target_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_events');
    }
};
