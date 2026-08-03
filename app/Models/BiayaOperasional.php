<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BiayaOperasional extends Model
{
    protected $table = 'biaya_operasional';

    protected $fillable = ['tanggal', 'kategori', 'keterangan', 'nominal'];

    protected $casts = [
        'tanggal' => 'date',
        'nominal' => 'decimal:2',
    ];
}