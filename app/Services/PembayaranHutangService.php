<?php

namespace App\Services;

use App\Models\BahanBakuMasuk;
use App\Models\JurnalUmum;
use App\Models\PembayaranHutang;
use App\Models\StokMasuk;
use Illuminate\Support\Facades\DB;
use Exception;

class PembayaranHutangService
{
    public function catatPembayaran(array $data): PembayaranHutang
    {
        return DB::transaction(function () use ($data) {
            $record = $this->cariSumber($data['sumber_tipe'], $data['sumber_id']);

            if (! $record) {
                throw new Exception('Transaksi sumber tidak ditemukan.');
            }

            if ($record->status_pembayaran === 'lunas') {
                throw new Exception('Transaksi ini sudah tercatat lunas.');
            }

            $pembayaran = PembayaranHutang::create($data);

            $record->update(['status_pembayaran' => 'lunas']);

            $akun = config('akun');
            $keterangan = 'Pembayaran hutang: ' . class_basename($record) . ' #' . $record->id;

            JurnalUmum::create([
                'tanggal' => $pembayaran->tanggal,
                'kode_akun' => $akun['hutang_usaha'],
                'keterangan' => $keterangan,
                'debit' => $pembayaran->nominal,
                'kredit' => 0,
                'sumber_tipe' => 'pembayaran_hutang',
                'sumber_id' => $pembayaran->id,
            ]);

            JurnalUmum::create([
                'tanggal' => $pembayaran->tanggal,
                'kode_akun' => $akun['kas'],
                'keterangan' => $keterangan,
                'debit' => 0,
                'kredit' => $pembayaran->nominal,
                'sumber_tipe' => 'pembayaran_hutang',
                'sumber_id' => $pembayaran->id,
            ]);

            return $pembayaran;
        });
    }

    public function batalkanPembayaran(PembayaranHutang $pembayaran): void
    {
        DB::transaction(function () use ($pembayaran) {
            $record = $this->cariSumber($pembayaran->sumber_tipe, $pembayaran->sumber_id);

            if ($record) {
                $record->update(['status_pembayaran' => 'belum_lunas']);
            }

            JurnalUmum::where('sumber_tipe', 'pembayaran_hutang')
                ->where('sumber_id', $pembayaran->id)
                ->delete();

            $pembayaran->delete();
        });
    }

    private function cariSumber(string $tipe, int $id): BahanBakuMasuk|StokMasuk|null
    {
        return match ($tipe) {
            'bahan_baku_masuk' => BahanBakuMasuk::find($id),
            'stok_masuk' => StokMasuk::find($id),
            default => null,
        };
    }
}