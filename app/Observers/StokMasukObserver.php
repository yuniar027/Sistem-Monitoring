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
     */
    public function created(StokMasuk $stokMasuk): void
    {
        DB::transaction(function () use ($stokMasuk) {
            // Lock produk row for update to get latest isi_per_satuan_beli
            $produk = $stokMasuk->produk()->lockForUpdate()->first();

            if (! $produk) {
                throw new Exception('Produk not found for SKU ' . $stokMasuk->sku);
            }

            $isiPerSatuan = (int) $produk->isi_per_satuan_beli;

            $kuantitasPcs = (int) $stokMasuk->kuantitas * $isiPerSatuan;

            // Ensure stok_mentah exists for the SKU
            StokMentah::firstOrCreate([
                'sku' => $stokMasuk->sku,
            ], [
                'kuantitas_tersedia' => 0,
            ]);

            // Atomic increment to avoid race conditions. Also update timestamp.
            StokMentah::where('sku', $stokMasuk->sku)
                ->increment('kuantitas_tersedia', $kuantitasPcs, ['updated_at' => now()]);
        });
    }
}
