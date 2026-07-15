<?php

namespace App\Services;

use App\Models\AlokasiEtalase;
use App\Models\StokPaket;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AlokasiEtalaseService
{
    public function buatAlokasi(array $data): AlokasiEtalase
    {
        $sku = $data['sku'];
        $quantity = (int) $data['kuantitas_dialokasikan'];

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'kuantitas_dialokasikan' => 'Kuantitas alokasi harus lebih dari 0.',
            ]);
        }

        return DB::transaction(function () use ($sku, $quantity, $data) {
            $stokPaketRecords = StokPaket::where('sku', $sku)
                ->where('status', 'belum_distribusi')
                ->orderBy('tanggal_dibuat')
                ->lockForUpdate()
                ->get();

            $availableStock = $stokPaketRecords->sum(fn (StokPaket $stokPaket) => $stokPaket->kuantitas_per_paket * $stokPaket->jumlah_paket);

            if ($availableStock < $quantity) {
                throw ValidationException::withMessages([
                    'kuantitas_dialokasikan' => 'Kuantitas alokasi (' . $quantity . ') melebihi stok paket belum distribusi (' . $availableStock . ').',
                ]);
            }

            $remainingQuantity = $quantity;

            foreach ($stokPaketRecords as $stokPaket) {
                if ($remainingQuantity <= 0) {
                    break;
                }

                $rowTotal = $stokPaket->kuantitas_per_paket * $stokPaket->jumlah_paket;

                if ($remainingQuantity >= $rowTotal) {
                    $stokPaket->jumlah_paket = 0;
                    $stokPaket->status = 'sudah_distribusi';
                    $stokPaket->save();
                    $remainingQuantity -= $rowTotal;
                    continue;
                }

                if ($remainingQuantity % $stokPaket->kuantitas_per_paket !== 0) {
                    throw ValidationException::withMessages([
                        'kuantitas_dialokasikan' => 'Kuantitas alokasi harus kelipatan dari ukuran paket yang tersedia.',
                    ]);
                }

                $packagesToConsume = intdiv($remainingQuantity, $stokPaket->kuantitas_per_paket);
                $stokPaket->jumlah_paket -= $packagesToConsume;
                if ($stokPaket->jumlah_paket <= 0) {
                    $stokPaket->jumlah_paket = 0;
                    $stokPaket->status = 'sudah_distribusi';
                }

                $stokPaket->save();
                $remainingQuantity = 0;
                break;
            }

            return AlokasiEtalase::create([
                'sku' => $sku,
                'channel' => $data['channel'],
                'nama_toko' => $data['nama_toko'],
                'kuantitas_dialokasikan' => $quantity,
                'kuantitas_terjual' => 0,
                'kuantitas_sisa' => $quantity,
                'tanggal_alokasi' => $data['tanggal_alokasi'] ?? now()->toDateString(),
                'status' => 'aktif',
            ]);
        });
    }
}
