<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PembayaranHutang extends Model
{
    protected $table = 'pembayaran_hutang';

    protected $fillable = [
        'tanggal',
        'sumber_tipe',
        'sumber_id',
        'nominal',
        'keterangan',
    ];
}