<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BahanBakuStok extends Model
{
    use HasFactory;

    protected $table = 'bahan_baku_stok';

    public $incrementing = false;
    protected $primaryKey = 'bahan_baku_id';
    public $timestamps = false;

    protected $fillable = [
        'bahan_baku_id',
        'kuantitas_tersedia',
        'updated_at',
    ];

    protected $casts = [
        'updated_at' => 'datetime',
    ];

    public function bahanBaku()
    {
        return $this->belongsTo(BahanBaku::class, 'bahan_baku_id');
    }
}
