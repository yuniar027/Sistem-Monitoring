<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaranPemetaanBahanBaku extends Model
{
    protected $table = 'saran_pemetaan_bahan_baku';

    protected $fillable = [
        'nama_item',
        'kode_bahan_disarankan',
        'nama_bahan',
        'metode',
        'catatan',
    ];
}
