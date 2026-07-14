<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StokMasuk extends Model
{
    use HasFactory;

    protected $table = 'stok_masuk';

    protected $fillable = [
        'tanggal', 'sku', 'vendor', 'kuantitas', 'harga_satuan', 'biaya_kirim', 'total_nominal',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'harga_satuan' => 'decimal:2',
        'biaya_kirim' => 'decimal:2',
        'total_nominal' => 'decimal:2',
    ];

    public function produk()
    {
        return $this->belongsTo(ProdukMaster::class, 'sku', 'sku');
    }
}
