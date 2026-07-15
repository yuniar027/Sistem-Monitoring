<?php

namespace App\Services;

use App\Models\ProdukMaster;
use App\Models\StokMentah;
use App\Models\StokPaket;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StokPaketService
{
    public function buatPaket(array $data): StokPaket
    {
        $sku = $data['sku'];
        $quantityPerPackage = (int) $data['kuantitas_per_paket'];
        $packageCount = (int) $data['jumlah_paket'];

        if ($quantityPerPackage <= 0 || $packageCount <= 0) {
            throw ValidationException::withMessages([
                'kuantitas_per_paket' => 'Kuantitas per paket dan jumlah paket harus lebih dari 0.',
            ]);
        }

        $totalQuantity = $quantityPerPackage * $packageCount;

        return DB::transaction(function () use ($sku, $quantityPerPackage, $packageCount, $totalQuantity, $data) {
            $stokMentah = StokMentah::where('sku', $sku)->lockForUpdate()->first();

            if (! $stokMentah) {
                throw ValidationException::withMessages([
                    'sku' => 'SKU tidak ditemukan di stok mentah.',
                ]);
            }

            if ($totalQuantity > $stokMentah->kuantitas_tersedia) {
                throw ValidationException::withMessages([
                    'kuantitas_per_paket' => 'Total kuantitas paket (' . $totalQuantity . ') melebihi stok mentah tersedia (' . $stokMentah->kuantitas_tersedia . ').',
                ]);
            }

            $stokMentah->decrement('kuantitas_tersedia', $totalQuantity, ['updated_at' => now()]);

            $stokPaket = StokPaket::create([
                'sku' => $sku,
                'kuantitas_per_paket' => $quantityPerPackage,
                'jumlah_paket' => $packageCount,
                'tanggal_dibuat' => $data['tanggal_dibuat'] ?? now()->toDateString(),
                'status' => 'belum_distribusi',
            ]);

            return $stokPaket;
        });
    }
}
