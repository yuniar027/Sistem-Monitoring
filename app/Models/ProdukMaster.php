<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProdukMaster extends Model
{
    use HasFactory;

    protected $table = 'produk_master';

    protected $fillable = [
        'sku', 'nama_produk', 'satuan_jual', 'satuan_beli', 'isi_per_satuan_beli', 'kategori', 'harga_modal_default', 'target_stok_minimum',
    ];

    protected $casts = [
        'isi_per_satuan_beli' => 'integer',
        'harga_modal_default' => 'decimal:2',
        'target_stok_minimum' => 'integer',
    ];

    public function stokMasuk()
    {
        return $this->hasMany(StokMasuk::class, 'sku', 'sku');
    }

    public function stokMentah()
    {
        return $this->hasOne(StokMentah::class, 'sku', 'sku');
    }

    public function stokPaket()
    {
        return $this->hasMany(StokPaket::class, 'sku', 'sku');
    }

    public function alokasiEtalase()
    {
        return $this->hasMany(AlokasiEtalase::class, 'sku', 'sku');
    }

    public function tugasPacking()
    {
        return $this->hasMany(TugasPacking::class, 'sku', 'sku');
    }

    public function transaksiPenjualan()
    {
        return $this->hasMany(TransaksiPenjualan::class, 'sku', 'sku');
    }
}
