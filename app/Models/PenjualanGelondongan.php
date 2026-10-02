<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenjualanGelondongan extends Model
{
    protected $table = 'penjualan_gelondongan';

    protected $fillable = [
        'tanggal',
        'keterangan',
        'channel',
        'nominal_penjualan',
        'nominal_hpp',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'nominal_penjualan' => 'decimal:2',
        'nominal_hpp' => 'decimal:2',
    ];

    public function getLabaKotorAttribute(): float
    {
        return (float) $this->nominal_penjualan - (float) $this->nominal_hpp;
    }
}
