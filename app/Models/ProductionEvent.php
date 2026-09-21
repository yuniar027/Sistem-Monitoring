<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionEvent extends Model
{
    protected $table = 'production_events';

    protected $fillable = [
        'tanggal',
        'barang_gudang_id',
        'production_process_target_id',
        'source_quantity',
        'multiplier_snapshot',
        'output_quantity',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'source_quantity' => 'decimal:2',
        'multiplier_snapshot' => 'decimal:2',
        'output_quantity' => 'decimal:2',
    ];

    public function barangGudang(): BelongsTo
    {
        return $this->belongsTo(StokBarangGudang::class);
    }

    public function productionProcessTarget(): BelongsTo
    {
        return $this->belongsTo(ProductionProcessTarget::class);
    }
}
