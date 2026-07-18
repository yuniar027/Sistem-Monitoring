<?php

namespace App\Services;

use App\Models\BahanBaku;
use App\Models\BahanBakuMasuk;
use App\Models\BahanBakuStok;
use App\Models\JurnalUmum;
use Illuminate\Support\Facades\DB;
use Exception;

class BahanBakuMasukService
{
    public function catatBahanBakuMasuk(array $data): BahanBakuMasuk
    {
        return DB::transaction(function () use ($data) {
            $bahanBakuMasuk = BahanBakuMasuk::withoutEvents(function () use ($data) {
                return BahanBakuMasuk::create($data);
            });

            $bahanBaku = BahanBaku::where('id', $bahanBakuMasuk->bahan_baku_id)->lockForUpdate()->first();

            if (! $bahanBaku) {
                throw new Exception('Bahan baku not found for ID ' . $bahanBakuMasuk->bahan_baku_id);
            }

            $isiPerSatuan = (int) $bahanBaku->isi_per_satuan_beli;
            $kuantitasPcs = (int) $bahanBakuMasuk->kuantitas * $isiPerSatuan;

            BahanBakuStok::firstOrCreate([
                'bahan_baku_id' => $bahanBakuMasuk->bahan_baku_id,
            ], [
                'kuantitas_tersedia' => 0,
            ]);

            BahanBakuStok::where('bahan_baku_id', $bahanBakuMasuk->bahan_baku_id)
                ->increment('kuantitas_tersedia', $kuantitasPcs, ['updated_at' => now()]);

            // Pencatatan jurnal otomatis: debit persediaan, kredit kas
            $akun = config('akun');
            $nominal = $bahanBakuMasuk->total_nominal;

            JurnalUmum::create([
                'tanggal' => $bahanBakuMasuk->tanggal,
                'kode_akun' => $akun['persediaan'],
                'keterangan' => 'Pembelian bahan baku: ' . $bahanBaku->nama_bahan,
                'debit' => $nominal,
                'kredit' => 0,
                'sumber_tipe' => 'bahan_baku_masuk',
                'sumber_id' => $bahanBakuMasuk->id,
            ]);

            JurnalUmum::create([
                'tanggal' => $bahanBakuMasuk->tanggal,
                'kode_akun' => $akun['kas'],
                'keterangan' => 'Pembelian bahan baku: ' . $bahanBaku->nama_bahan,
                'debit' => 0,
                'kredit' => $nominal,
                'sumber_tipe' => 'bahan_baku_masuk',
                'sumber_id' => $bahanBakuMasuk->id,
            ]);

            return $bahanBakuMasuk;
        });
    }
}