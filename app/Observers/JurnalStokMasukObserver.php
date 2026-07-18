<?php

namespace App\Observers;

use App\Models\JurnalUmum;
use App\Models\StokMasuk;
use Illuminate\Support\Facades\DB;

class JurnalStokMasukObserver
{
    public function created(StokMasuk $stokMasuk): void
    {
        DB::transaction(function () use ($stokMasuk) {
            $akun = config('akun');
            $tanggal = $stokMasuk->tanggal;
            $nominal = $stokMasuk->total_nominal;

            JurnalUmum::create([
                'tanggal' => $tanggal,
                'kode_akun' => $akun['persediaan'],
                'keterangan' => 'Pembelian stok masuk: ' . $stokMasuk->sku,
                'debit' => $nominal,
                'kredit' => 0,
                'sumber_tipe' => 'stok_masuk',
                'sumber_id' => $stokMasuk->id,
            ]);

            JurnalUmum::create([
                'tanggal' => $tanggal,
                'kode_akun' => $akun['kas'],
                'keterangan' => 'Pembelian stok masuk: ' . $stokMasuk->sku,
                'debit' => 0,
                'kredit' => $nominal,
                'sumber_tipe' => 'stok_masuk',
                'sumber_id' => $stokMasuk->id,
            ]);
        });
    }
}