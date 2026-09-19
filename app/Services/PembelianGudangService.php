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
                $barang = StokBarangGudang::query()
                    ->whereKey($detail['barang_gudang_id'])
                    ->where('kategori', StokBarangGudang::KATEGORI_ORIGAMI)
                    ->first();

                if (! $barang) {
                    throw new RuntimeException(
                        'Barang yang dipilih bukan barang Origami atau tidak ditemukan.'
                    );
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
                    throw new RuntimeException(
                        "Harga acuan belum tersedia untuk barang: {$barang->nama_barang}"
                    );
                }

                $kuantitas = (float) $detail['kuantitas'];
                $hargaInvoice = (float) $detail['harga_invoice'];
                $hargaAcuanValue = (float) $hargaAcuan->harga_acuan;

                $nilaiAcuan = $kuantitas * $hargaAcuanValue;
                $nilaiInvoice = $kuantitas * $hargaInvoice;
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

            return $pembelian;
        });
    }
}