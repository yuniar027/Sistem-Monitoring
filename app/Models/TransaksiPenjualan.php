<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransaksiPenjualan extends Model
{
    use HasFactory;

    protected $table = 'transaksi_penjualan';

    protected $fillable = [
        'channel', 'no_pesanan', 'no_resi', 'sku', 'jumlah', 'harga', 'total', 'tanggal', 'status_order',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'harga' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function produk()
    {
        return $this->belongsTo(ProdukMaster::class, 'sku', 'sku');
    }
}
