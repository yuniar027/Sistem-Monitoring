<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionProcessTarget extends Model
{
    protected $table = 'production_process_targets';

    protected $fillable = [
        'production_process_id',
        'variasi_gudang_id',
        'multiplier',
    ];

    protected $casts = [
        'multiplier' => 'decimal:2',
    ];

    public function productionProcess(): BelongsTo
    {
        return $this->belongsTo(ProductionProcess::class);
    }

    public function variasiGudang(): BelongsTo
    {
        return $this->belongsTo(StokVariasiGudang::class);
    }

    public function productionEvents(): HasMany
    {
        return $this->hasMany(ProductionEvent::class);
    }
}
