<?php

namespace App\Observers;

use App\Models\ErrorLog;
use App\Models\JurnalUmum;
use App\Models\ProdukMaster;
use App\Models\TransaksiPenjualan;
use Illuminate\Support\Facades\DB;

class JurnalPenjualanObserver
{
    public function created(TransaksiPenjualan $penjualan): void
    {
        DB::transaction(function () use ($penjualan) {
            $akun = config('akun');

            JurnalUmum::create([
                'tanggal' => $penjualan->tanggal,
                'kode_akun' => $akun['kas'],
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

            if (! $produk || (float) ($produk->harga_modal_default ?? 0) <= 0) {
                ErrorLog::create([
                    'source' => 'jurnal.penjualan.hpp',
                    'payload' => ['sku' => $penjualan->sku, 'no_pesanan' => $penjualan->no_pesanan],
                    'error_message' => sprintf('HPP tidak bisa dihitung, harga_modal_default kosong untuk SKU %s', $penjualan->sku),
                    'resolved' => false,
                    'created_at' => now(),
                ]);

                return;
            }

            $hpp = (float) $produk->harga_modal_default * (float) $penjualan->jumlah;

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
        });
    }
}