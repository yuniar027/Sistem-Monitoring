<?php

namespace App\Services;

use App\Models\BiayaOperasional;
use App\Models\JurnalUmum;
use Illuminate\Support\Facades\DB;

class BiayaOperasionalService
{
    public function catatBiaya(array $data): BiayaOperasional
    {
        return DB::transaction(function () use ($data) {
            $biaya = BiayaOperasional::create($data);

            $akun = config('akun');
            $namaKategori = config('kategori_biaya.' . $biaya->kategori, $biaya->kategori);

            JurnalUmum::create([
                'tanggal' => $biaya->tanggal,
                'kode_akun' => $akun['biaya_operasional'],
                'keterangan' => $namaKategori . ($biaya->keterangan ? ': ' . $biaya->keterangan : ''),
                'debit' => $biaya->nominal,
                'kredit' => 0,
                'sumber_tipe' => 'biaya_operasional',
                'sumber_id' => $biaya->id,
            ]);

            JurnalUmum::create([
                'tanggal' => $biaya->tanggal,
                'kode_akun' => $akun['kas'],
                'keterangan' => $namaKategori . ($biaya->keterangan ? ': ' . $biaya->keterangan : ''),
                'debit' => 0,
                'kredit' => $biaya->nominal,
                'sumber_tipe' => 'biaya_operasional',
                'sumber_id' => $biaya->id,
            ]);

            return $biaya;
        });
    }
}