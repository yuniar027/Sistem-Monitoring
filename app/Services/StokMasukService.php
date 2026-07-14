<?php

namespace App\Services;

use App\Models\StokMasuk;
use App\Models\StokMentah;
use App\Models\ProdukMaster;
use Illuminate\Support\Facades\DB;
use Exception;

class StokMasukService
{
    /**
     * Record incoming stock and update raw stock (pcs) within a DB transaction.
     *
     * @param array $data
     * @return StokMasuk
     * @throws Exception
     */
    public function catatStokMasuk(array $data): StokMasuk
    {
        return DB::transaction(function () use ($data) {
            // Create stok_masuk without firing model events (observer is a safety net)
            $stokMasuk = StokMasuk::withoutEvents(function () use ($data) {
                return StokMasuk::create($data);
            });

            // Lock produk row to get latest isi_per_satuan_beli
            $produk = ProdukMaster::where('sku', $stokMasuk->sku)->lockForUpdate()->first();

            if (! $produk) {
                throw new Exception('Produk not found for SKU ' . $stokMasuk->sku);
            }

            $isiPerSatuan = (int) $produk->isi_per_satuan_beli;
            $kuantitasPcs = (int) $stokMasuk->kuantitas * $isiPerSatuan;

            // Ensure stok_mentah exists
            StokMentah::firstOrCreate([
                'sku' => $stokMasuk->sku,
            ], [
                'kuantitas_tersedia' => 0,
            ]);

            // Atomic increment on stok_mentah
            StokMentah::where('sku', $stokMasuk->sku)
                ->increment('kuantitas_tersedia', $kuantitasPcs, ['updated_at' => now()]);

            return $stokMasuk;
        });
    }
}
