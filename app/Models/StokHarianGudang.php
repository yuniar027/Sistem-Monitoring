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
            if (! $harian->wasRecentlyCreated && $harian->wasChanged(['rak', 'input'])) {
                static::rippleForward($harian);
            }
        });
    }

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

    public function alokasiKhusus(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StokAlokasiKhususHarian::class, 'barang_gudang_id', 'barang_gudang_id')
            ->where('tanggal', $this->tanggal);
    }

    /**
     * Event produksi (Produksi K) yang memakai barang ini sebagai SOURCE,
     * di-scope ke tanggal yang sama dengan snapshot harian ini.
     */
    public function productionEvents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductionEvent::class, 'barang_gudang_id', 'barang_gudang_id')
            ->where('tanggal', $this->tanggal);
    }

    protected function stokSiap(): Attribute
    {
        return Attribute::make(
            get: fn () => (float) $this->rak + (float) $this->input,
        );
    }

    /**
     * STOK AKHIR = STOK SIAP - total alokasi khusus - total source_quantity
     * yang dipakai proses Produksi K pada tanggal yang sama. Barang yang
     * jadi SOURCE produksi (mis. "...BT") ikut berkurang stoknya persis
     * seperti formula Excel aslinya (=E2-F2-G2-...).
     */
    protected function stokAkhir(): Attribute
    {
        return Attribute::make(
            get: function () {
                $totalAlokasiKhusus = StokAlokasiKhususHarian::where('barang_gudang_id', $this->barang_gudang_id)
                    ->whereDate('tanggal', $this->tanggal)
                    ->sum('kuantitas');

                $totalKonsumsiProduksi = ProductionEvent::where('barang_gudang_id', $this->barang_gudang_id)
                    ->whereDate('tanggal', $this->tanggal)
                    ->sum('source_quantity');

                return $this->stok_siap - (float) $totalAlokasiKhusus - (float) $totalKonsumsiProduksi;
            },
        );
    }

    protected function permintaanH(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->stok_akhir - (float) ($this->barangGudang?->stok_aman ?? 0),
        );
    }
}