<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TugasPacking extends Model
{
    use HasFactory;

    protected $table = 'tugas_packing';

    protected $fillable = [
        'sku', 'channel_tujuan', 'kuantitas', 'status', 'ditugaskan_ke', 'tanggal_dibuat', 'tanggal_selesai',
    ];

    protected $casts = [
        'tanggal_dibuat' => 'date',
        'tanggal_selesai' => 'date',
    ];

    public function produk()
    {
        return $this->belongsTo(ProdukMaster::class, 'sku', 'sku');
    }

    public function assignedTo()
    {
        return $this->belongsTo(Packer::class, 'ditugaskan_ke');
    }
}
