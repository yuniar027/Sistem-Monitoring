<?php

namespace App\Filament\Widgets;

use App\Models\JurnalUmum;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class ArusKasWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Arus Kas';
    protected ?string $description = 'Ringkasan kas masuk dan kas keluar untuk periode terpilih.';

    protected function getStats(): array
    {
        $tanggalAwal = $this->pageFilters['tanggal_awal'] ?? null;
        $tanggalAkhir = $this->pageFilters['tanggal_akhir'] ?? null;

        $query = JurnalUmum::where('kode_akun', config('akun.kas'));

        if ($tanggalAwal) {
            $query->whereDate('tanggal', '>=', $tanggalAwal);
        }
        if ($tanggalAkhir) {
            $query->whereDate('tanggal', '<=', $tanggalAkhir);
        }

        $rows = (clone $query)
            ->selectRaw('sumber_tipe, SUM(debit) as total_masuk, SUM(kredit) as total_keluar')
            ->groupBy('sumber_tipe')
            ->get()
            ->keyBy('sumber_tipe');

        $format = fn ($angka) => 'Rp ' . number_format($angka, 0, ',', '.');

        $kasMasukPenjualan = (float) ($rows['transaksi_penjualan']->total_masuk ?? 0);
        $kasKeluarBiaya = (float) ($rows['biaya_operasional']->total_keluar ?? 0);
        $kasKeluarHutang = (float) ($rows['pembayaran_hutang']->total_keluar ?? 0);

        $totalMasuk = $kasMasukPenjualan;
        $totalKeluar = $kasKeluarBiaya + $kasKeluarHutang;
        $arusKasBersih = $totalMasuk - $totalKeluar;

        return [
            Stat::make('Kas Masuk (Penjualan)', $format($kasMasukPenjualan))
                ->color('success'),
            Stat::make('Kas Keluar (Biaya Operasional)', $format($kasKeluarBiaya))
                ->color('danger'),
            Stat::make('Kas Keluar (Bayar Hutang)', $format($kasKeluarHutang))
                ->color('danger'),
            Stat::make('Arus Kas Bersih', $format($arusKasBersih))
                ->description($arusKasBersih >= 0 ? 'Kas bertambah' : 'Kas berkurang')
                ->color($arusKasBersih >= 0 ? 'success' : 'danger'),
        ];
    }
}