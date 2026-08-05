<?php

namespace App\Filament\Widgets;

use App\Models\JurnalUmum;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class LabaRugiWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Laporan Laba Rugi';
    protected ?string $description = 'Ikhtisar pendapatan, HPP, biaya operasional, dan laba bersih.';

    protected function getStats(): array
    {
        $tanggalAwal = $this->pageFilters['tanggal_awal'] ?? null;
        $tanggalAkhir = $this->pageFilters['tanggal_akhir'] ?? null;

        $query = JurnalUmum::query();

        if ($tanggalAwal) {
            $query->whereDate('tanggal', '>=', $tanggalAwal);
        }

        if ($tanggalAkhir) {
            $query->whereDate('tanggal', '<=', $tanggalAkhir);
        }

        $rows = $query
            ->selectRaw('kode_akun, SUM(debit) as total_debit, SUM(kredit) as total_kredit')
            ->groupBy('kode_akun')
            ->get()
            ->keyBy('kode_akun');

        $penjualan = (float) (($rows['4100']->total_kredit ?? 0) - ($rows['4100']->total_debit ?? 0));
        $hpp = (float) (($rows['5100']->total_debit ?? 0) - ($rows['5100']->total_kredit ?? 0));
        $biayaOperasional = (float) (($rows['6100']->total_debit ?? 0) - ($rows['6100']->total_kredit ?? 0));

        $labaKotor = $penjualan - $hpp;
        $labaBersih = $labaKotor - $biayaOperasional;

        $format = fn ($angka) => 'Rp ' . number_format($angka, 0, ',', '.');

        return [
            Stat::make('Pendapatan (Penjualan)', $format($penjualan))
                ->color('success'),
            Stat::make('HPP', $format($hpp))
                ->color('danger'),
            Stat::make('Laba Kotor', $format($labaKotor))
                ->color($labaKotor >= 0 ? 'success' : 'danger'),
            Stat::make('Biaya Operasional', $format($biayaOperasional))
                ->color('danger'),
            Stat::make('Laba Bersih', $format($labaBersih))
                ->description($labaBersih >= 0 ? 'Untung' : 'Rugi')
                ->color($labaBersih >= 0 ? 'success' : 'danger'),
        ];
    }
}