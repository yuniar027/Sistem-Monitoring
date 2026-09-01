<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokVariasiHarian extends Model
{
    protected $table = 'stok_variasi_harian';

    protected $fillable = [
        'variasi_gudang_id',
        'tanggal',
        'stok_awal',
        'input',
        'out',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'stok_awal' => 'decimal:2',
        'input' => 'decimal:2',
        'out' => 'decimal:2',
    ];

    public function variasiGudang(): BelongsTo
    {
        return $this->belongsTo(StokVariasiGudang::class, 'variasi_gudang_id');
    }

    protected static function booted(): void
    {
        static::saved(function (self $variasiHarian) {
            if ($variasiHarian->wasRecentlyCreated || ! $variasiHarian->wasChanged(['stok_awal', 'input', 'out'])) {
                return;
            }

            // Table 2 (variasi) independen -- cuma ripple ke dirinya sendiri,
            // TIDAK mempengaruhi stok_akhir barang induk (Table 1).
            static::rippleForward($variasiHarian);
        });
    }

    /**
     * Update stok_awal di tanggal-tanggal SETELAH $acuan supaya selalu
     * lanjut dari sisa hari sebelumnya, mirip formula spreadsheet.
     */
    public static function rippleForward(self $acuan): void
    {
        $selanjutnya = static::where('variasi_gudang_id', $acuan->variasi_gudang_id)
            ->whereDate('tanggal', '>', $acuan->tanggal)
            ->orderBy('tanggal')
            ->get();

        foreach ($selanjutnya as $hari) {
            $stokAwalBaru = $acuan->sisa;

            if ((float) $hari->stok_awal !== (float) $stokAwalBaru) {
                $hari->stok_awal = $stokAwalBaru;
                $hari->saveQuietly();
            }

            $acuan = $hari;
        }
    }

    /**
     * STOK HASIL = STOK AWAL + INPUT
     */
    protected function stokHasil(): Attribute
    {
        return Attribute::make(
            get: fn () => (float) $this->stok_awal + (float) $this->input,
        );
    }

    /**
     * SISA = STOK HASIL - OUT
     */
    protected function sisa(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->stok_hasil - (float) $this->out,
        );
    }

    /**
     * S M UMMA = STOK AWAL - INPUT
     */
    protected function sMUmma(): Attribute
    {
        return Attribute::make(
            get: fn () => (float) $this->stok_awal - (float) $this->input,
        );
    }

    /**
     * PERMINTAAN H barang induk pada tanggal yang sama dengan variasi ini.
     */
    protected function permintaanH(): Attribute
    {
        return Attribute::make(
            get: function () {
                $barang = $this->variasiGudang?->barangGudang;
                $harianBarang = $barang?->harianPadaTanggal($this->tanggal);

                return $harianBarang?->permintaan_h;
            },
        );
    }
}