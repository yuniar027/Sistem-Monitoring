<?php

namespace App\Observers;

use App\Models\StokMasuk;
use App\Models\StokMentah;
use Illuminate\Support\Facades\DB;
use Exception;

class StokMasukObserver
{
    /**
     * Handle the StokMasuk "created" event.
     *
     * CATATAN: Jalur utama pembuatan StokMasuk (lewat form Filament /
     * StokMasukService::catatStokMasuk()) sengaja pakai withoutEvents(),
     * jadi observer ini TIDAK terpanggil di jalur itu. Logic yang sama
     * (increment StokMentah, buat StokPaket untuk produk simple) sudah
     * dipindahkan langsung ke StokMasukService. Observer ini dibiarkan
     * terdaftar untuk jaga-jaga kalau ada jalur lain yang create StokMasuk
     * tanpa withoutEvents().
     */
    public function created(StokMasuk $stokMasuk): void
    {
        DB::transaction(function () use ($stokMasuk) {
            $produk = $stokMasuk->produk()->lockForUpdate()->first();

            if (! $produk) {
                throw new Exception('Produk not found for SKU ' . $stokMasuk->sku);
            }

            $isiPerSatuan = (int) $produk->isi_per_satuan_beli;
            $kuantitasPcs = (int) $stokMasuk->kuantitas * $isiPerSatuan;

            StokMentah::firstOrCreate([
                'sku' => $stokMasuk->sku,
            ], [
                'kuantitas_tersedia' => 0,
            ]);

            StokMentah::where('sku', $stokMasuk->sku)
                ->increment('kuantitas_tersedia', $kuantitasPcs, ['updated_at' => now()]);
        });
    }
}