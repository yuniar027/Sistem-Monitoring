<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlokasiEtalase extends Model
{
    use HasFactory;

    protected $table = 'alokasi_etalase';

    protected $fillable = [
        'sku', 'channel', 'nama_toko', 'kuantitas_dialokasikan', 'kuantitas_terjual', 'kuantitas_sisa', 'tanggal_alokasi', 'status',
    ];

    protected $casts = [
        'tanggal_alokasi' => 'date',
    ];

    public function produk()
    {
        return $this->belongsTo(ProdukMaster::class, 'sku', 'sku');
    }
}
