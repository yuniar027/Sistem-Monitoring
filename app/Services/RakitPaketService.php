<?php

namespace App\Services;

use App\Models\BahanBakuStok;
use App\Models\ResepPaketItem;
use App\Models\StokPaket;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RakitPaketService
{
    public function rakitPaket(array $data): StokPaket
    {
        $sku = $data['sku'];
        $jumlahPaket = (int) $data['jumlah_paket'];
        $tanggalDibuat = $data['tanggal_dibuat'] ?? now()->toDateString();

        if ($jumlahPaket <= 0) {
            throw ValidationException::withMessages([
                'jumlah_paket' => 'Jumlah paket harus lebih dari 0.',
            ]);
        } 

        return DB::transaction(function () use ($sku, $jumlahPaket, $tanggalDibuat) {
            $resepItems = ResepPaketItem::where('sku', $sku)->get();

            if ($resepItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'sku' => 'Resep untuk SKU ini belum didefinisikan.',
                ]);
            }

            $kebutuhan = [];
            $kurang = [];

            foreach ($resepItems as $item) {
                $stok = BahanBakuStok::where('bahan_baku_id', $item->bahan_baku_id)
                    ->lockForUpdate()
                    ->first();

                $tersedia = $stok ? $stok->kuantitas_tersedia : 0;
                $butuh = $item->kuantitas_per_paket * $jumlahPaket;

                $kebutuhan[$item->bahan_baku_id] = $butuh;

                if ($tersedia < $butuh) {
                    $nama = $item->bahanBaku->nama_bahan ?? ('Bahan baku #' . $item->bahan_baku_id);
                    $kurang[] = sprintf(
                        '%s (butuh %d, tersedia %d, kurang %d)',
                        $nama,
                        $butuh,
                        $tersedia,
                        $butuh - $tersedia
                    );
                }
            }

            if (! empty($kurang)) {
                throw ValidationException::withMessages([
                    'jumlah_paket' => 'Stok bahan baku tidak cukup, silakan restock ke supplier: ' . implode('; ', $kurang),
                ]);
            }

            foreach ($kebutuhan as $bahanBakuId => $butuh) {
                BahanBakuStok::where('bahan_baku_id', $bahanBakuId)
                    ->decrement('kuantitas_tersedia', $butuh, ['updated_at' => now()]);
            }

            return StokPaket::create([
                'sku' => $sku,
                'kuantitas_per_paket' => 1,
                'jumlah_paket' => $jumlahPaket,
                'tanggal_dibuat' => $tanggalDibuat,
                'status' => 'belum_distribusi',
            ]);
        });
    }
}