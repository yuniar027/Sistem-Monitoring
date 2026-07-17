<?php

namespace App\Services;

use App\Models\BahanBakuMasuk;
use App\Models\BahanBakuStok;
use App\Models\BahanBaku;
use Illuminate\Support\Facades\DB;
use Exception;

class BahanBakuMasukService
{
    /**
     * Record incoming bahan baku and update bahan baku stock (pcs) within a DB transaction.
     *
     * @param array $data
     * @return BahanBakuMasuk
     * @throws Exception
     */
    public function catatBahanBakuMasuk(array $data): BahanBakuMasuk
    {
        return DB::transaction(function () use ($data) {
            // Create bahan_baku_masuk without firing model events
            $bahanBakuMasuk = BahanBakuMasuk::withoutEvents(function () use ($data) {
                return BahanBakuMasuk::create($data);
            });

            // Lock bahan_baku row to get latest isi_per_satuan_beli
            $bahanBaku = BahanBaku::where('id', $bahanBakuMasuk->bahan_baku_id)->lockForUpdate()->first();

            if (! $bahanBaku) {
                throw new Exception('Bahan baku not found for ID ' . $bahanBakuMasuk->bahan_baku_id);
            }

            $isiPerSatuan = (int) $bahanBaku->isi_per_satuan_beli;
            $kuantitasPcs = (int) $bahanBakuMasuk->kuantitas * $isiPerSatuan;

            // Ensure bahan_baku_stok exists
            BahanBakuStok::firstOrCreate([
                'bahan_baku_id' => $bahanBakuMasuk->bahan_baku_id,
            ], [
                'kuantitas_tersedia' => 0,
            ]);

            // Atomic increment on bahan_baku_stok
            BahanBakuStok::where('bahan_baku_id', $bahanBakuMasuk->bahan_baku_id)
                ->increment('kuantitas_tersedia', $kuantitasPcs, ['updated_at' => now()]);

            return $bahanBakuMasuk;
        });
    }
}
