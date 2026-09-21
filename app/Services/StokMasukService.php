<?php

namespace App\Services;

use App\Models\HargaAcuanOrigami;
use App\Models\JurnalUmum;
use App\Models\PerbandinganHargaStokMasuk;
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

            $this->bandingkanHarga($produk, $stokMasuk);

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

    /**
     * Bandingkan harga_satuan stok masuk ini terhadap Harga Acuan Origami
     * yang sedang aktif untuk SKU tersebut, lalu simpan sebagai snapshot di
     * perbandingan_harga_stok_masuk — SATU baris untuk SETIAP stok masuk
     * (bukan hanya yang beda harga), supaya nilai stok berdasarkan Harga
     * Acuan selalu tercatat per transaksi.
     *
     * PENTING: ini murni pencatatan & deteksi selisih. Harga Acuan Origami
     * itu sendiri TIDAK pernah diubah di sini — perubahan hanya terjadi
     * lewat approval manual (lihat HargaAcuanOrigamiService::tetapkanHargaAktif).
     *
     * Dipanggil dari satu titik (catatStokMasuk) yang dipakai baik oleh
     * form manual (CreateStokMasuk) maupun import massal via webhook n8n
     * (ProcessStokMasukWebhook), jadi logic ini otomatis konsisten di
     * kedua jalur itu.
     *
     * Catatan: ini BUKAN modul HPP/profit — tidak ada perhitungan harga
     * jual, margin, atau laba di sini.
     */
    protected function bandingkanHarga(ProdukMaster $produk, StokMasuk $stokMasuk): void
    {
        $acuanAktif = HargaAcuanOrigami::aktifUntuk($stokMasuk->sku);

        $hargaAcuan = $acuanAktif?->harga_acuan !== null ? (float) $acuanAktif->harga_acuan : null;
        $hargaInvoice = (float) $stokMasuk->harga_satuan;
        $kuantitas = (int) $stokMasuk->kuantitas;
        $nilaiInvoice = $kuantitas * $hargaInvoice;

        if ($hargaAcuan === null) {
            PerbandinganHargaStokMasuk::create([
                'stok_masuk_id' => $stokMasuk->id,
                'sku' => $stokMasuk->sku,
                'harga_acuan_origami_id' => null,
                'harga_acuan' => null,
                'harga_invoice' => $hargaInvoice,
                'kuantitas' => $kuantitas,
                'nilai_acuan' => null,
                'nilai_invoice' => $nilaiInvoice,
                'selisih_nominal' => null,
                'persen_selisih' => null,
                'kategori' => 'baru',
                'status_approval' => 'pending',
            ]);

            return;
        }

        $nilaiAcuan = $kuantitas * $hargaAcuan;
        $selisihNominal = $nilaiInvoice - $nilaiAcuan;
        $persenSelisih = round((($hargaInvoice - $hargaAcuan) / $hargaAcuan) * 100, 2);

        // Toleransi pembulatan kecil (< Rp0,01/unit) supaya harga yang
        // identik tidak dianggap "berubah".
        if (abs($hargaInvoice - $hargaAcuan) < 0.01) {
            $kategori = 'tetap';
            $statusApproval = 'tidak_perlu';
        } else {
            $kategori = $persenSelisih > 0 ? 'naik' : 'turun';
            $statusApproval = 'pending';
        }

        PerbandinganHargaStokMasuk::create([
            'stok_masuk_id' => $stokMasuk->id,
            'sku' => $stokMasuk->sku,
            'harga_acuan_origami_id' => $acuanAktif->id,
            'harga_acuan' => $hargaAcuan,
            'harga_invoice' => $hargaInvoice,
            'kuantitas' => $kuantitas,
            'nilai_acuan' => $nilaiAcuan,
            'nilai_invoice' => $nilaiInvoice,
            'selisih_nominal' => $selisihNominal,
            'persen_selisih' => $persenSelisih,
            'kategori' => $kategori,
            'status_approval' => $statusApproval,
        ]);
    }
}