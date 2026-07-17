<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResepPaketItem extends Model
{
    protected $table = 'resep_paket_item';

    protected $fillable = [
        'sku',
        'bahan_baku_id',
        'kuantitas_per_paket',
    ];

    protected $casts = [
        'kuantitas_per_paket' => 'integer',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(ProdukMaster::class, 'sku', 'sku');
    }

    public function bahanBaku(): BelongsTo
    {
        return $this->belongsTo(BahanBaku::class, 'bahan_baku_id', 'id');
    }
}
