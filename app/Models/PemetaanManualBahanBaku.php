<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PemetaanManualBahanBaku extends Model
{
    protected $table = 'pemetaan_manual_bahan_baku';

    protected $fillable = ['nama_item', 'bahan_baku_id'];

    public function bahanBaku()
    {
        return $this->belongsTo(BahanBaku::class, 'bahan_baku_id');
    }
}