<?php

namespace App\Services;

use App\Models\HargaAcuanOrigami;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HargaAcuanOrigamiService
{
    /**
     * Tetapkan Harga Acuan Origami baru yang aktif untuk satu SKU.
     *
     * Kalau sudah ada Harga Acuan aktif sebelumnya, baris lama itu TIDAK
     * dihapus atau ditimpa — hanya ditandai berakhir (is_active = false,
     * berlaku_sampai = sehari sebelum berlaku_mulai yang baru), supaya
     * histori tetap bisa dilacak sebagai deret waktu.
     *
     * Idempotent: kalau Harga Acuan yang sedang aktif untuk SKU ini sudah
     * persis sama (harga & berlaku_mulai sama), tidak ada perubahan yang
     * dibuat — baris aktif yang sudah ada langsung dikembalikan. Ini
     * penting supaya import ulang file Excel yang sama, atau approval yang
     * diklik dua kali, tidak menghasilkan histori duplikat.
     *
     * Ini SATU-SATUNYA tempat yang boleh mengubah Harga Acuan Origami —
     * dipakai baik oleh aksi approve di halaman "Perbandingan Harga Stok
     * Masuk" maupun oleh HargaAcuanOrigamiImporter (import awal dari Excel).
     */
    public function tetapkanHargaAktif(
        string $sku,
        float $hargaAcuan,
        string|Carbon $berlakuMulai,
        ?string $catatan = null
    ): HargaAcuanOrigami {
        $berlakuMulai = Carbon::parse($berlakuMulai)->startOfDay();

        return DB::transaction(function () use ($sku, $hargaAcuan, $berlakuMulai, $catatan) {
            $acuanLama = HargaAcuanOrigami::where('sku', $sku)
                ->aktif()
                ->orderByDesc('berlaku_mulai')
                ->lockForUpdate()
                ->first();

            $sudahSama = $acuanLama
                && abs((float) $acuanLama->harga_acuan - $hargaAcuan) < 0.01
                && $acuanLama->berlaku_mulai->isSameDay($berlakuMulai);

            if ($sudahSama) {
                return $acuanLama;
            }

            if ($acuanLama) {
                $acuanLama->update([
                    'is_active' => false,
                    'berlaku_sampai' => $berlakuMulai->copy()->subDay(),
                ]);
            }

            return HargaAcuanOrigami::create([
                'sku' => $sku,
                'harga_acuan' => $hargaAcuan,
                'berlaku_mulai' => $berlakuMulai,
                'berlaku_sampai' => null,
                'is_active' => true,
                'catatan' => $catatan,
            ]);
        });
    }
}
