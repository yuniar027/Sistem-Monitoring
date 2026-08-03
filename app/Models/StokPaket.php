<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StokPaket extends Model
{
    use HasFactory;

    protected $table = 'stok_paket';

    protected $fillable = [
        'sku', 'kuantitas_per_paket', 'jumlah_paket', 'jumlah_target', 'jumlah_reject', 'persentase_reject', 'status_reject', 'tanggal_dibuat', 'status',
    ];

    protected $casts = [
        'tanggal_dibuat' => 'date',
    ];

    public function produk()
    {
        return $this->belongsTo(ProdukMaster::class, 'sku', 'sku');
    }
}
