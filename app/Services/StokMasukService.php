<?php

namespace App\Services;

use App\Models\JurnalUmum;
use App\Models\ProdukMaster;
use App\Models\StokMasuk;
use App\Models\StokMentah;
use Illuminate\Support\Facades\DB;
use Exception;

class StokMasukService
{
    public function catatStokMasuk(array $data): StokMasuk
    {
        return DB::transaction(function () use ($data) {
            $stokMasuk = StokMasuk::withoutEvents(function () use ($data) {
                return StokMasuk::create($data);
            });

            $produk = ProdukMaster::where('sku', $stokMasuk->sku)->lockForUpdate()->first();

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

            // Pencatatan jurnal otomatis: debit persediaan, kredit kas
            $akun = config('akun');
            $nominal = $stokMasuk->total_nominal;

            JurnalUmum::create([
                'tanggal' => $stokMasuk->tanggal,
                'kode_akun' => $akun['persediaan'],
                'keterangan' => 'Pembelian stok masuk: ' . $stokMasuk->sku,
                'debit' => $nominal,
                'kredit' => 0,
                'sumber_tipe' => 'stok_masuk',
                'sumber_id' => $stokMasuk->id,
            ]);

            JurnalUmum::create([
                'tanggal' => $stokMasuk->tanggal,
                'kode_akun' => $akun['kas'],
                'keterangan' => 'Pembelian stok masuk: ' . $stokMasuk->sku,
                'debit' => 0,
                'kredit' => $nominal,
                'sumber_tipe' => 'stok_masuk',
                'sumber_id' => $stokMasuk->id,
            ]);

            return $stokMasuk;
        });
    }
}