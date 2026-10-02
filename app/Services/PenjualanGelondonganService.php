<?php

namespace App\Services;

use App\Models\JurnalUmum;
use App\Models\PenjualanGelondongan;
use Illuminate\Support\Facades\DB;

class PenjualanGelondonganService
{
    public const SUMBER_TIPE = 'penjualan_gelondongan';

    public function catat(array $data): PenjualanGelondongan
    {
        return DB::transaction(function () use ($data) {
            $penjualan = PenjualanGelondongan::create($this->rapikan($data));

            $this->buatJurnal($penjualan);

            return $penjualan;
        });
    }

    /**
     * Ubah penjualan sekaligus menyesuaikan jurnalnya
     * (jurnal lama dibuang, dibuat ulang dari data terbaru).
     */
    public function perbarui(PenjualanGelondongan $penjualan, array $data): PenjualanGelondongan
    {
        return DB::transaction(function () use ($penjualan, $data) {
            $penjualan->update($this->rapikan($data));

            $this->hapusJurnal($penjualan);
            $this->buatJurnal($penjualan->refresh());

            return $penjualan;
        });
    }

    public function hapus(PenjualanGelondongan $penjualan): void
    {
        DB::transaction(function () use ($penjualan) {
            $this->hapusJurnal($penjualan);
            $penjualan->delete();
        });
    }

    private function rapikan(array $data): array
    {
        $data['nominal_hpp'] = $data['nominal_hpp'] ?? 0;

        return $data;
    }

    private function hapusJurnal(PenjualanGelondongan $penjualan): void
    {
        JurnalUmum::query()
            ->where('sumber_tipe', self::SUMBER_TIPE)
            ->where('sumber_id', $penjualan->id)
            ->delete();
    }

    private function buatJurnal(PenjualanGelondongan $penjualan): void
    {
        $akun = config('akun');

        $label = trim(($penjualan->channel ? $penjualan->channel . ' - ' : '') . ($penjualan->keterangan ?? ''));
        $label = $label !== '' ? $label : 'Penjualan gelondongan';

        $baris = function (string $kodeAkun, string $keterangan, float $debit, float $kredit) use ($penjualan) {
            JurnalUmum::create([
                'tanggal' => $penjualan->tanggal,
                'kode_akun' => $kodeAkun,
                'keterangan' => $keterangan,
                'debit' => $debit,
                'kredit' => $kredit,
                'sumber_tipe' => self::SUMBER_TIPE,
                'sumber_id' => $penjualan->id,
            ]);
        };

        $penjualanNominal = (float) $penjualan->nominal_penjualan;
        $hpp = (float) $penjualan->nominal_hpp;

        // Kas masuk dari penjualan.
        $baris($akun['kas'], 'Penjualan gelondongan: ' . $label, $penjualanNominal, 0);
        $baris($akun['penjualan'], 'Penjualan gelondongan: ' . $label, 0, $penjualanNominal);

        // HPP hanya dijurnal kalau diisi.
        if ($hpp > 0) {
            $baris($akun['hpp'], 'HPP penjualan gelondongan: ' . $label, $hpp, 0);
            $baris($akun['persediaan'], 'HPP penjualan gelondongan: ' . $label, 0, $hpp);
        }
    }
}
