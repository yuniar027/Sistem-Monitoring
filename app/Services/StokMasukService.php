<?php

namespace App\Services;

use App\Models\JurnalUmum;
use App\Models\ProdukMaster;
use App\Models\StokMasuk;
use App\Models\StokMentah;
use App\Models\StokPaket;
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

            // Produk tipe "simple" tidak melalui proses rakit (RakitPaketService).
            // Konfirmasi Umma: begitu barang datang dari pabrik, langsung bisa dijual.
            // Maka begitu masuk gudang, langsung dianggap siap distribusi — dicatat
            // sebagai StokPaket juga, supaya AlokasiEtalaseService bisa membacanya.
            if ($produk->tipe_produk === 'simple') {
                StokPaket::create([
                    'sku' => $stokMasuk->sku,
                    'kuantitas_per_paket' => 1,
                    'jumlah_paket' => $kuantitasPcs,
                    'tanggal_dibuat' => $stokMasuk->tanggal,
                    'status' => 'belum_distribusi',
                ]);
            }

            // Pencatatan jurnal otomatis: debit persediaan, kredit hutang usaha
            // (konfirmasi Umma: pembelian pabrik selalu tempo, dibayar mingguan)
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
                'kode_akun' => $akun['hutang_usaha'],
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