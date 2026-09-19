<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HargaAcuanOrigami extends Model
{
    use HasFactory;

    protected $table = 'harga_acuan_origami';

    protected $fillable = [
        'barang_gudang_id',
        'harga_acuan',
        'berlaku_mulai',
        'berlaku_sampai',
        'is_active',
        'catatan',
    ];

    protected $casts = [
        'harga_acuan' => 'decimal:2',
        'berlaku_mulai' => 'date',
        'berlaku_sampai' => 'date',
        'is_active' => 'boolean',
    ];

    public function barangGudang(): BelongsTo
    {
        return $this->belongsTo(
            StokBarangGudang::class,
            'barang_gudang_id'
        );
    }

    public function pembelianDetail(): HasMany
    {
        return $this->hasMany(
            PembelianGudangDetail::class,
            'harga_acuan_id'
        );
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Mengambil harga acuan aktif untuk satu barang gudang.
     */
    public static function aktifUntuk(int $barangGudangId): ?self
    {
        return static::query()
            ->where('barang_gudang_id', $barangGudangId)
            ->aktif()
            ->orderByDesc('berlaku_mulai')
            ->first();
    }
}