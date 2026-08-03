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
        $jumlahTarget = (int) $data['jumlah_target'];
        $jumlahJadi = (int) $data['jumlah_jadi'];
        $tanggalDibuat = $data['tanggal_dibuat'] ?? now()->toDateString();

        if ($jumlahTarget <= 0) {
            throw ValidationException::withMessages([
                'jumlah_target' => 'Jumlah target harus lebih dari 0.',
            ]);
        }

        if ($jumlahJadi < 0) {
            throw ValidationException::withMessages([
                'jumlah_jadi' => 'Jumlah jadi tidak boleh negatif.',
            ]);
        }

        if ($jumlahJadi > $jumlahTarget) {
            throw ValidationException::withMessages([
                'jumlah_jadi' => 'Jumlah jadi tidak boleh lebih besar dari jumlah target.',
            ]);
        }

        return DB::transaction(function () use ($sku, $jumlahTarget, $jumlahJadi, $tanggalDibuat) {
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
                $butuh = $item->kuantitas_per_paket * $jumlahTarget;

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
                    'jumlah_target' => 'Stok bahan baku tidak cukup, silakan restock ke supplier: ' . implode('; ', $kurang),
                ]);
            }

            foreach ($kebutuhan as $bahanBakuId => $butuh) {
                BahanBakuStok::where('bahan_baku_id', $bahanBakuId)
                    ->decrement('kuantitas_tersedia', $butuh, ['updated_at' => now()]);
            }

            $jumlahReject = $jumlahTarget - $jumlahJadi;
            $persentaseReject = $jumlahTarget > 0 ? round(($jumlahReject / $jumlahTarget) * 100, 2) : 0;
            $batasAman = config('kualitas.batas_reject_persen');
            $statusReject = $persentaseReject > $batasAman ? 'melebihi_batas' : 'normal';

            return StokPaket::create([
                'sku' => $sku,
                'kuantitas_per_paket' => 1,
                'jumlah_paket' => $jumlahJadi,
                'jumlah_target' => $jumlahTarget,
                'jumlah_reject' => $jumlahReject,
                'persentase_reject' => $persentaseReject,
                'status_reject' => $statusReject,
                'tanggal_dibuat' => $tanggalDibuat,
                'status' => 'belum_distribusi',
            ]);
        });
    }
}