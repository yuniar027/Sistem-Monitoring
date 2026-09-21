<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerbandinganHargaStokMasuk extends Model
{
    protected $table = 'perbandingan_harga_stok_masuk';

    protected $fillable = [
        'stok_masuk_id',
        'sku',
        'harga_acuan_origami_id',
        'harga_acuan',
        'harga_invoice',
        'kuantitas',
        'nilai_acuan',
        'nilai_invoice',
        'selisih_nominal',
        'persen_selisih',
        'kategori',
        'status_approval',
        'catatan',
        'diproses_pada',
    ];

    protected $casts = [
        'harga_acuan' => 'decimal:2',
        'harga_invoice' => 'decimal:2',
        'nilai_acuan' => 'decimal:2',
        'nilai_invoice' => 'decimal:2',
        'selisih_nominal' => 'decimal:2',
        'persen_selisih' => 'decimal:2',
        'diproses_pada' => 'datetime',
    ];

    public function produk()
    {
        return $this->belongsTo(ProdukMaster::class, 'sku', 'sku');
    }

    public function stokMasuk()
    {
        return $this->belongsTo(StokMasuk::class, 'stok_masuk_id');
    }

    /**
     * Harga Acuan Origami yang sedang berlaku pada saat transaksi ini
     * dicatat (snapshot referensi, BUKAN acuan yang berlaku sekarang).
     */
    public function hargaAcuanOrigami()
    {
        return $this->belongsTo(HargaAcuanOrigami::class, 'harga_acuan_origami_id');
    }
}
