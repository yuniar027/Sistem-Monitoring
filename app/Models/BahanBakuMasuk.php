<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BahanBakuMasuk extends Model
{
    use HasFactory;

    protected $table = 'bahan_baku_masuk';

    protected $fillable = [
        'bahan_baku_id',
        'tanggal',
        'vendor',
        'kuantitas',
        'harga_satuan',
        'biaya_kirim',
        'total_nominal',
        'status_pembayaran',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'harga_satuan' => 'decimal:2',
        'biaya_kirim' => 'decimal:2',
        'total_nominal' => 'decimal:2',
    ];

    public function bahanBaku()
    {
        return $this->belongsTo(BahanBaku::class, 'bahan_baku_id');
    }
}
