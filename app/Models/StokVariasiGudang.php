<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class StokVariasiGudang extends Model
{
    use HasFactory;

    protected $table = 'stok_variasi_gudang';

    protected $fillable = [
        'barang_gudang_id',
        'kode_variasi',
        'stok_aman',
    ];

    protected $casts = [
        'stok_aman' => 'decimal:2',
    ];

    public function barangGudang(): BelongsTo
    {
        return $this->belongsTo(StokBarangGudang::class, 'barang_gudang_id');
    }

    public function harian(): HasMany
    {
        return $this->hasMany(StokVariasiHarian::class, 'variasi_gudang_id');
    }

    /**
     * Ambil snapshot harian untuk tanggal tertentu (default: hari ini).
     * Return null kalau belum di-generate oleh stok:generate-harian.
     */
    public function harianPadaTanggal(?string $tanggal = null): ?StokVariasiHarian
    {
        $tanggal = $tanggal ? Carbon::parse($tanggal) : today();

        return $this->harian()->whereDate('tanggal', $tanggal)->first();
    }
}