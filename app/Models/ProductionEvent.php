<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ProductionEvent extends Model
{
    protected $table = 'production_events';

    protected $fillable = [
        'tanggal',
        'barang_gudang_id',
        'production_process_target_id',
        'source_quantity',
        'multiplier_snapshot',
        'output_quantity',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'source_quantity' => 'decimal:2',
        'multiplier_snapshot' => 'decimal:2',
        'output_quantity' => 'decimal:2',
    ];

    /**
     * Snapshot state SEBELUM update, diambil di event 'updating' selagi
     * $this->original masih murni nilai lama. Dipakai di 'updated' untuk
     * membatalkan efek lama sebelum menerapkan efek baru.
     */
    private ?array $stateSebelumUpdate = null;

    public function barangGudang(): BelongsTo
    {
        return $this->belongsTo(StokBarangGudang::class);
    }

    public function productionProcessTarget(): BelongsTo
    {
        return $this->belongsTo(ProductionProcessTarget::class);
    }

    protected static function booted(): void
    {
        // Efek sebuah ProductionEvent ada di DUA sisi:
        // 1) SOURCE (barang_gudang_id): stok_akhir barang itu berkurang
        //    source_quantity -- sudah otomatis lewat StokHarianGudang::stokAkhir(),
        //    tapi tanggal-tanggal SETELAHNYA (kolom rak, tersimpan) perlu
        //    di-ripple ulang, sama seperti pola StokAlokasiKhususHarian.
        // 2) TARGET (variasi_gudang_id lewat production_process_target_id):
        //    output_quantity ditambahkan ke StokVariasiHarian.produksi_input
        //    pada tanggal yang sama.

        static::created(function (self $event) {
            static::terapkanEfek($event);
        });

        static::updating(function (self $event) {
            $event->stateSebelumUpdate = [
                'tanggal' => $event->getOriginal('tanggal'),
                'barang_gudang_id' => (int) $event->getOriginal('barang_gudang_id'),
                'production_process_target_id' => (int) $event->getOriginal('production_process_target_id'),
                'output_quantity' => (float) $event->getOriginal('output_quantity'),
            ];
        });

        static::updated(function (self $event) {
            if ($event->stateSebelumUpdate !== null) {
                static::batalkanEfek($event->stateSebelumUpdate);
                $event->stateSebelumUpdate = null;
            }

            static::terapkanEfek($event);
        });

        static::deleted(function (self $event) {
            static::batalkanEfek([
                'tanggal' => $event->tanggal,
                'barang_gudang_id' => (int) $event->barang_gudang_id,
                'production_process_target_id' => (int) $event->production_process_target_id,
                'output_quantity' => (float) $event->output_quantity,
            ]);
        });
    }

    private static function terapkanEfek(self $event): void
    {
        static::sinkronVariasi(
            (int) $event->production_process_target_id,
            static::tanggalKe($event->tanggal),
            (float) $event->output_quantity,
        );

        static::rippleStokSumber((int) $event->barang_gudang_id, static::tanggalKe($event->tanggal));
    }

    private static function batalkanEfek(array $state): void
    {
        $tanggal = static::tanggalKe($state['tanggal']);

        static::sinkronVariasi(
            $state['production_process_target_id'],
            $tanggal,
            -1 * $state['output_quantity'],
        );

        static::rippleStokSumber($state['barang_gudang_id'], $tanggal);
    }

    private static function sinkronVariasi(int $targetId, string $tanggal, float $deltaOutput): void
    {
        $target = ProductionProcessTarget::find($targetId);

        if (! $target) {
            return;
        }

        $variasiHarian = StokVariasiHarian::untukSinkronProduksi($target->variasi_gudang_id, $tanggal);
        $variasiHarian->produksi_input = (float) $variasiHarian->produksi_input + $deltaOutput;
        $variasiHarian->save();
    }

    private static function rippleStokSumber(int $barangGudangId, string $tanggal): void
    {
        $harianBarang = StokHarianGudang::where('barang_gudang_id', $barangGudangId)
            ->whereDate('tanggal', $tanggal)
            ->first();

        if ($harianBarang) {
            StokHarianGudang::rippleForward($harianBarang);
        }
    }

    private static function tanggalKe(Carbon|string $tanggal): string
    {
        return ($tanggal instanceof Carbon ? $tanggal->copy() : Carbon::parse($tanggal))->toDateString();
    }
}