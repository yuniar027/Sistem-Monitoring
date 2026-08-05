<?php

namespace App\Filament\Widgets;

use App\Models\JurnalUmum;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RingkasanJurnalWidget extends BaseWidget
{
    protected ?string $heading = 'Ringkasan Jurnal Umum';
    protected ?string $description = 'Cek total debit, total kredit, dan selisih untuk validasi pembukuan.';

    protected function getStats(): array
    {
        $totalDebit = JurnalUmum::sum('debit');
        $totalKredit = JurnalUmum::sum('kredit');
        $selisih = $totalDebit - $totalKredit;

        return [
            Stat::make('Total Debit', 'Rp ' . number_format($totalDebit, 0, ',', '.'))
                ->color('success'),
            Stat::make('Total Kredit', 'Rp ' . number_format($totalKredit, 0, ',', '.'))
                ->color('danger'),
            Stat::make('Selisih (harus 0)', 'Rp ' . number_format($selisih, 0, ',', '.'))
                ->description($selisih == 0 ? 'Buku besar seimbang' : 'Ada ketidaksesuaian, perlu dicek')
                ->color($selisih == 0 ? 'success' : 'warning'),
        ];
    }
}
