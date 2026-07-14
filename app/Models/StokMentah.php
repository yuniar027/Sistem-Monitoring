<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StokMentah extends Model
{
    use HasFactory;

    protected $table = 'stok_mentah';

    protected $primaryKey = 'sku';
    public $incrementing = false;
    protected $keyType = 'string';

    const CREATED_AT = null;
    const UPDATED_AT = 'updated_at';
    public $timestamps = true;

    protected $fillable = [
        'sku', 'kuantitas_tersedia', 'updated_at',
    ];

    public function produk()
    {
        return $this->belongsTo(ProdukMaster::class, 'sku', 'sku');
    }
}
