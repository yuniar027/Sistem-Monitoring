<?php

namespace App\Services;

use App\Models\AlokasiEtalase;
use App\Models\ErrorLog;
use App\Models\JurnalUmum;
use App\Models\ProdukMaster;
use App\Models\TransaksiPenjualan;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class TransaksiPenjualanService
{
    public function catatPenjualan(array $data): TransaksiPenjualan
    {
        return DB::transaction(function () use ($data) {
            try {
                $penjualan = TransaksiPenjualan::create([
                    'channel' => $data['channel'],
                    'no_pesanan' => $data['no_pesanan'],
                    'no_resi' => $data['no_resi'],
                    'sku' => $data['sku'],
                    'jumlah' => $data['jumlah'],
                    'harga' => $data['harga'],
                    'total' => $data['total'],
                    'tanggal' => $data['tanggal'],
                    'status_order' => $data['status_order'],
                ]);
            } catch (QueryException $exception) {
                if (str_contains($exception->getMessage(), 'duplicate key') || str_contains($exception->getMessage(), 'unique')) {
                    return TransaksiPenjualan::where('no_pesanan', $data['no_pesanan'])->first();
                }
                throw $exception;
            }

            $alokasi = AlokasiEtalase::where('sku', $data['sku'])
                ->where('channel', $data['channel'])
                ->where('status', 'aktif')
                ->orderBy('tanggal_alokasi')
                ->lockForUpdate()
                ->first();

            if ($alokasi) {
                $originalSisa = $alokasi->kuantitas_sisa;
                $alokasi->kuantitas_sisa -= $data['jumlah'];
                $alokasi->kuantitas_terjual += $data['jumlah'];
                $alokasi->save();

                if ($alokasi->kuantitas_sisa < 0) {
                    ErrorLog::create([
                        'source' => 'webhook.penjualan',
                        'payload' => $data,
                        'error_message' => sprintf('Kuantitas sisa inkonsisten: %s -> %s untuk SKU %s channel %s', $originalSisa, $alokasi->kuantitas_sisa, $data['sku'], $data['channel']),
                        'resolved' => false,
                        'created_at' => now(),
                    ]);
                }
            } else {
                ErrorLog::create([
                    'source' => 'webhook.penjualan',
                    'payload' => $data,
                    'error_message' => sprintf('Tidak ada alokasi etalase aktif untuk SKU %s channel %s', $data['sku'], $data['channel']),
                    'resolved' => false,
                    'created_at' => now(),
                ]);
            }

            $this->catatJurnalPenjualan($penjualan);

            return $penjualan;
        });
    }

    /**
     * Pencatatan jurnal otomatis untuk penjualan:
     * 1. Pengakuan pendapatan: debit piutang usaha, kredit penjualan
     * 2. HPP (jika harga_modal_default produk sudah terisi): debit HPP, kredit persediaan
     */
    private function catatJurnalPenjualan(TransaksiPenjualan $penjualan): void
    {
        $akun = config('akun');

        JurnalUmum::create([
            'tanggal' => $penjualan->tanggal,
            'kode_akun' => $akun['piutang_usaha'],
            'keterangan' => 'Penjualan ' . $penjualan->channel . ': ' . $penjualan->no_pesanan,
            'debit' => $penjualan->total,
            'kredit' => 0,
            'sumber_tipe' => 'transaksi_penjualan',
            'sumber_id' => $penjualan->id,
        ]);

        JurnalUmum::create([
            'tanggal' => $penjualan->tanggal,
            'kode_akun' => $akun['penjualan'],
            'keterangan' => 'Penjualan ' . $penjualan->channel . ': ' . $penjualan->no_pesanan,
            'debit' => 0,
            'kredit' => $penjualan->total,
            'sumber_tipe' => 'transaksi_penjualan',
            'sumber_id' => $penjualan->id,
        ]);

        $produk = ProdukMaster::where('sku', $penjualan->sku)->first();

        if (! $produk || $produk->harga_modal_default === null) {
            ErrorLog::create([
                'source' => 'jurnal.penjualan.hpp',
                'payload' => ['sku' => $penjualan->sku, 'no_pesanan' => $penjualan->no_pesanan],
                'error_message' => sprintf('HPP tidak dicatat: harga_modal_default belum diisi untuk SKU %s', $penjualan->sku),
                'resolved' => false,
                'created_at' => now(),
            ]);

            return;
        }

        $hpp = $produk->harga_modal_default * $penjualan->jumlah;

        JurnalUmum::create([
            'tanggal' => $penjualan->tanggal,
            'kode_akun' => $akun['hpp'],
            'keterangan' => 'HPP penjualan: ' . $penjualan->no_pesanan,
            'debit' => $hpp,
            'kredit' => 0,
            'sumber_tipe' => 'transaksi_penjualan',
            'sumber_id' => $penjualan->id,
        ]);

        JurnalUmum::create([
            'tanggal' => $penjualan->tanggal,
            'kode_akun' => $akun['persediaan'],
            'keterangan' => 'HPP penjualan: ' . $penjualan->no_pesanan,
            'debit' => 0,
            'kredit' => $hpp,
            'sumber_tipe' => 'transaksi_penjualan',
            'sumber_id' => $penjualan->id,
        ]);
    }
}
