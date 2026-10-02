<?php

namespace App\Services;

use App\Models\HargaAcuanOrigami;
use App\Models\PembelianGudang;
use App\Models\StokBarangGudang;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PembelianGudangService
{
    public function simpanPembelian(array $data): PembelianGudang
    {
        return DB::transaction(function () use ($data) {
            $tanggal = Carbon::parse($data['tanggal']);
            $detailInput = $data['detail'] ?? [];
            $hargaAcuanKosong = [];

            if (empty($detailInput)) {
                throw new RuntimeException(
                    'Minimal harus ada satu barang dalam invoice.'
                );
            }

            $pembelian = PembelianGudang::create([
                'nomor_invoice' => $data['nomor_invoice'],
                'tanggal' => $tanggal->toDateString(),
                'supplier' => $data['supplier'] ?? null,
                'catatan' => $data['catatan'] ?? null,
            ]);

            foreach ($detailInput as $detail) {
                $barang = null;

                if (! empty($detail['barang_gudang_id'])) {
                    $barang = StokBarangGudang::query()
                        ->whereKey($detail['barang_gudang_id'])
                        ->first();
                }

                $kuantitas = (float) ($detail['kuantitas'] ?? 0);
                $hargaInvoice = (float) ($detail['harga_invoice'] ?? 0);
                $nilaiInvoice = $kuantitas * $hargaInvoice;

                /*
                 * Barang belum ditemukan di Master Barang Gudang.
                 * Tetap simpan data invoice untuk proses review.
                 */
                if (! $barang) {
                    $pembelian->detail()->create([
                        'barang_gudang_id' => null,
                        'kode_barang_invoice' => $detail['kode_barang_invoice'] ?? null,
                        'nama_barang_invoice' => $detail['nama_barang_invoice'] ?? null,
                        'status_pemetaan' => 'perlu_review',
                        'harga_acuan_id' => null,
                        'kuantitas' => $kuantitas,
                        'harga_invoice' => $hargaInvoice,
                        'harga_acuan_snapshot' => null,
                        'nilai_acuan' => null,
                        'nilai_invoice' => $nilaiInvoice,
                        'selisih_nominal' => null,
                        'persen_selisih' => null,
                        'kategori_perbandingan' => null,
                        'status_approval' => 'pending',
                        'catatan' => $detail['catatan'] ?? null,
                    ]);

                    continue;
                }

                $hargaAcuan = HargaAcuanOrigami::query()
                    ->where('barang_gudang_id', $barang->id)
                    ->where('is_active', true)
                    ->whereDate('berlaku_mulai', '<=', $tanggal)
                    ->where(function ($query) use ($tanggal) {
                        $query
                            ->whereNull('berlaku_sampai')
                            ->orWhereDate('berlaku_sampai', '>=', $tanggal);
                    })
                    ->orderByDesc('berlaku_mulai')
                    ->first();

                if (! $hargaAcuan) {
                    $hargaAcuanKosong[] = "{$barang->nama_barang} ({$barang->kode_barang})";

                    continue;
                }

                $hargaAcuanValue = (float) $hargaAcuan->harga_acuan;

                $nilaiAcuan = $kuantitas * $hargaAcuanValue;
                $selisihNominal = $nilaiInvoice - $nilaiAcuan;

                $persenSelisih = $hargaAcuanValue > 0
                    ? (($hargaInvoice - $hargaAcuanValue) / $hargaAcuanValue) * 100
                    : null;

                if ($hargaInvoice > $hargaAcuanValue) {
                    $kategori = 'naik';
                } elseif ($hargaInvoice < $hargaAcuanValue) {
                    $kategori = 'turun';
                } else {
                    $kategori = 'sama';
                }

                $statusApproval = $kategori === 'sama'
                    ? 'tidak_perlu'
                    : 'disetujui';

                $pembelian->detail()->create([
                    'barang_gudang_id' => $barang->id,
                    'kode_barang_invoice' => $detail['kode_barang_invoice'] ?? null,
                    'nama_barang_invoice' => $detail['nama_barang_invoice'] ?? null,
                    'status_pemetaan' => 'cocok',
                    'harga_acuan_id' => $hargaAcuan->id,
                    'kuantitas' => $kuantitas,
                    'harga_invoice' => $hargaInvoice,
                    'harga_acuan_snapshot' => $hargaAcuanValue,
                    'nilai_acuan' => $nilaiAcuan,
                    'nilai_invoice' => $nilaiInvoice,
                    'selisih_nominal' => $selisihNominal,
                    'persen_selisih' => $persenSelisih,
                    'kategori_perbandingan' => $kategori,
                    'status_approval' => $statusApproval,
                    'catatan' => $detail['catatan'] ?? null,
                ]);
            }

            if (! empty($hargaAcuanKosong)) {
                $daftar = array_unique($hargaAcuanKosong);

                // Dilempar di dalam transaksi, jadi semua data yang sudah dibuat dibatalkan.
                throw new RuntimeException(
                    'Harga acuan belum tersedia untuk ' . count($daftar) . ' barang: '
                    . implode('; ', $daftar)
                );
            }

            return $pembelian;
        });
    }
}