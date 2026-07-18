<?php

namespace App\Observers;

use App\Models\BahanBakuMasuk;
use App\Models\JurnalUmum;
use Illuminate\Support\Facades\DB;

class JurnalBahanBakuMasukObserver
{
    public function created(BahanBakuMasuk $bahanBakuMasuk): void
    {
        DB::transaction(function () use ($bahanBakuMasuk) {
            $akun = config('akun');
            $tanggal = $bahanBakuMasuk->tanggal;
            $nominal = $bahanBakuMasuk->total_nominal;

            JurnalUmum::create([
                'tanggal' => $tanggal,
                'kode_akun' => $akun['persediaan'],
                'keterangan' => 'Pembelian bahan baku: ' . ($bahanBakuMasuk->bahanBaku->nama_bahan ?? $bahanBakuMasuk->bahan_baku_id),
                'debit' => $nominal,
                'kredit' => 0,
                'sumber_tipe' => 'bahan_baku_masuk',
                'sumber_id' => $bahanBakuMasuk->id,
            ]);

            JurnalUmum::create([
                'tanggal' => $tanggal,
                'kode_akun' => $akun['kas'],
                'keterangan' => 'Pembelian bahan baku: ' . ($bahanBakuMasuk->bahanBaku->nama_bahan ?? $bahanBakuMasuk->bahan_baku_id),
                'debit' => 0,
                'kredit' => $nominal,
                'sumber_tipe' => 'bahan_baku_masuk',
                'sumber_id' => $bahanBakuMasuk->id,
            ]);
        });
    }
}