<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BahanBaku extends Model
{
    use HasFactory;

    protected $table = 'bahan_baku';

    protected $fillable = [
        'kode_bahan',
        'nama_bahan',
        'satuan_beli',
        'isi_per_satuan_beli',
    ];

    public function masuk()
    {
        return $this->hasMany(BahanBakuMasuk::class, 'bahan_baku_id');
    }

    public function stok()
    {
        return $this->hasOne(BahanBakuStok::class, 'bahan_baku_id');
    }
}
