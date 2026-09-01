<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokHarianGudang extends Model
{
    protected $table = 'stok_harian_gudang';

    protected $fillable = [
        'barang_gudang_id',
        'tanggal',
        'rak',
        'input',
        'um_titip_pabrik',
        'stok_mentah_umma',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'rak' => 'decimal:2',
        'input' => 'decimal:2',
        'um_titip_pabrik' => 'decimal:2',
        'stok_mentah_umma' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $harian) {
            // Kalau ini bukan pembuatan baris baru (generate-harian) dan
            // rak/input berubah, ripple ke tanggal-tanggal setelahnya.
            if (! $harian->wasRecentlyCreated && $harian->wasChanged(['rak', 'input'])) {
                static::rippleForward($harian);
            }
        });
    }

    /**
     * Update rak di tanggal-tanggal SETELAH $acuan supaya selalu
     * lanjut dari stok_akhir hari sebelumnya, mirip formula spreadsheet.
     * Pakai saveQuietly supaya tidak memicu event berulang (infinite loop),
     * karena rippling sudah dilakukan manual lewat loop ini.
     */
    public static function rippleForward(self $acuan): void
    {
        $selanjutnya = static::where('barang_gudang_id', $acuan->barang_gudang_id)
            ->whereDate('tanggal', '>', $acuan->tanggal)
            ->orderBy('tanggal')
            ->get();

        foreach ($selanjutnya as $hari) {
            $rakBaru = $acuan->stok_akhir;

            if ((float) $hari->rak !== (float) $rakBaru) {
                $hari->rak = $rakBaru;
                $hari->saveQuietly();
            }

            $acuan = $hari;
        }
    }

    public function barangGudang(): BelongsTo
    {
        return $this->belongsTo(StokBarangGudang::class, 'barang_gudang_id');
    }

    /**
     * Alokasi khusus (kolom K) di-scope ke barang & tanggal yang sama
     * dengan snapshot harian ini.
     */
    public function alokasiKhusus(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StokAlokasiKhususHarian::class, 'barang_gudang_id', 'barang_gudang_id')
            ->where('tanggal', $this->tanggal);
    }

    /**
     * STOK SIAP = RAK + INPUT (hari ini)
     */
    protected function stokSiap(): Attribute
    {
        return Attribute::make(
            get: fn () => (float) $this->rak + (float) $this->input,
        );
    }

    /**
     * STOK AKHIR = STOK SIAP - total kuantitas alokasi khusus (kolom K)
     * pada tanggal yang sama. Alokasi khusus dan variasi (Table 2) itu
     * dua hal yang independen -- variasi TIDAK mengurangi stok_akhir ini.
     */
    protected function stokAkhir(): Attribute
    {
        return Attribute::make(
            get: function () {
                $totalAlokasiKhusus = StokAlokasiKhususHarian::where('barang_gudang_id', $this->barang_gudang_id)
                    ->whereDate('tanggal', $this->tanggal)
                    ->sum('kuantitas');

                return $this->stok_siap - (float) $totalAlokasiKhusus;
            },
        );
    }

    /**
     * PERMINTAAN H = STOK AKHIR - STOK AMAN (dari master barang)
     */
    protected function permintaanH(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->stok_akhir - (float) ($this->barangGudang?->stok_aman ?? 0),
        );
    }
}