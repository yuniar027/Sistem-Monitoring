<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PembelianGudangDetail extends Model
{
    use HasFactory;

    protected $table = 'pembelian_gudang_detail';

    protected $fillable = [
        'pembelian_gudang_id',
        'barang_gudang_id',
        'kode_barang_invoice',
        'nama_barang_invoice',
        'status_pemetaan',
        'harga_acuan_id',
        'kuantitas',
        'harga_invoice',
        'harga_acuan_snapshot',
        'nilai_acuan',
        'nilai_invoice',
        'selisih_nominal',
        'persen_selisih',
        'kategori_perbandingan',
        'status_approval',
        'sudah_dicek',
        'dicek_pada',
        'catatan',
    ];

    protected $casts = [
        'kuantitas' => 'decimal:2',
        'harga_invoice' => 'decimal:2',
        'harga_acuan_snapshot' => 'decimal:2',
        'nilai_acuan' => 'decimal:2',
        'nilai_invoice' => 'decimal:2',
        'selisih_nominal' => 'decimal:2',
        'persen_selisih' => 'decimal:2',
        'sudah_dicek' => 'boolean',
        'dicek_pada' => 'datetime',
    ];

    public function pembelian(): BelongsTo
    {
        return $this->belongsTo(
            PembelianGudang::class,
            'pembelian_gudang_id'
        );
    }

    public function barangGudang(): BelongsTo
    {
        return $this->belongsTo(
            StokBarangGudang::class,
            'barang_gudang_id'
        );
    }

    public function hargaAcuan(): BelongsTo
    {
        return $this->belongsTo(
            HargaAcuanOrigami::class,
            'harga_acuan_id'
        );
    }
}