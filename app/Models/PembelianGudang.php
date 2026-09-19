<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PembelianGudang extends Model
{
    use HasFactory;

    protected $table = 'pembelian_gudang';

    protected $fillable = [
        'nomor_invoice',
        'tanggal',
        'supplier',
        'catatan',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function detail(): HasMany
    {
        return $this->hasMany(
            PembelianGudangDetail::class,
            'pembelian_gudang_id'
        );
    }
}