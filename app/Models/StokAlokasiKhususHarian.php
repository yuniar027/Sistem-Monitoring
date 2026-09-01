<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokAlokasiKhususHarian extends Model
{
    protected $table = 'stok_alokasi_khusus_harian';

    protected $fillable = [
        'barang_gudang_id',
        'tanggal',
        'kode_alokasi',
        'kuantitas',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'kuantitas' => 'decimal:2',
    ];

    public function barangGudang(): BelongsTo
    {
        return $this->belongsTo(StokBarangGudang::class, 'barang_gudang_id');
    }

    protected static function booted(): void
    {
        static::saved(function (self $alokasi) {
            // kuantitas berubah -> stok_akhir barang di tanggal itu berubah
            // -> ripple ke rak barang untuk tanggal-tanggal setelahnya
            $harianBarang = StokHarianGudang::where('barang_gudang_id', $alokasi->barang_gudang_id)
                ->whereDate('tanggal', $alokasi->tanggal)
                ->first();

            if ($harianBarang) {
                StokHarianGudang::rippleForward($harianBarang);
            }
        });

        static::deleted(function (self $alokasi) {
            $harianBarang = StokHarianGudang::where('barang_gudang_id', $alokasi->barang_gudang_id)
                ->whereDate('tanggal', $alokasi->tanggal)
                ->first();

            if ($harianBarang) {
                StokHarianGudang::rippleForward($harianBarang);
            }
        });
    }
}
