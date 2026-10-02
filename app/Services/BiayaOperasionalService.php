<?php

namespace App\Services;

use App\Models\BiayaOperasional;
use App\Models\JurnalUmum;
use Illuminate\Support\Facades\DB;

class BiayaOperasionalService
{
    public const SUMBER_TIPE = 'biaya_operasional';

    public function catatBiaya(array $data): BiayaOperasional
    {
        return DB::transaction(function () use ($data) {
            $biaya = BiayaOperasional::create($data);

            $this->buatJurnal($biaya);

            return $biaya;
        });
    }

    /**
     * Ubah biaya sekaligus menyesuaikan jurnalnya.
     * Jurnal lama dibuang lalu dibuat ulang dari data terbaru,
     * supaya tanggal/nominal/kategori di jurnal selalu sama dengan biaya.
     */
    public function perbaruiBiaya(BiayaOperasional $biaya, array $data): BiayaOperasional
    {
        return DB::transaction(function () use ($biaya, $data) {
            $biaya->update($data);

            $this->hapusJurnal($biaya);
            $this->buatJurnal($biaya->refresh());

            return $biaya;
        });
    }

    /**
     * Hapus biaya beserta jurnalnya, agar Saldo Kas dan laporan
     * tidak menyisakan jurnal yatim.
     */
    public function hapusBiaya(BiayaOperasional $biaya): void
    {
        DB::transaction(function () use ($biaya) {
            $this->hapusJurnal($biaya);
            $biaya->delete();
        });
    }

    private function hapusJurnal(BiayaOperasional $biaya): void
    {
        JurnalUmum::query()
            ->where('sumber_tipe', self::SUMBER_TIPE)
            ->where('sumber_id', $biaya->id)
            ->delete();
    }

    private function buatJurnal(BiayaOperasional $biaya): void
    {
        $akun = config('akun');
        $namaKategori = config('kategori_biaya.' . $biaya->kategori, $biaya->kategori);
        $keterangan = $namaKategori . ($biaya->keterangan ? ': ' . $biaya->keterangan : '');

        JurnalUmum::create([
            'tanggal' => $biaya->tanggal,
            'kode_akun' => $akun['biaya_operasional'],
            'keterangan' => $keterangan,
            'debit' => $biaya->nominal,
            'kredit' => 0,
            'sumber_tipe' => self::SUMBER_TIPE,
            'sumber_id' => $biaya->id,
        ]);

        JurnalUmum::create([
            'tanggal' => $biaya->tanggal,
            'kode_akun' => $akun['kas'],
            'keterangan' => $keterangan,
            'debit' => 0,
            'kredit' => $biaya->nominal,
            'sumber_tipe' => self::SUMBER_TIPE,
            'sumber_id' => $biaya->id,
        ]);
    }
}
