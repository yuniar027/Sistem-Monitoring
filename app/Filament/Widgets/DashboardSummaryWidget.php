<?php

namespace App\Filament\Widgets;

use App\Models\JurnalUmum;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardSummaryWidget extends BaseWidget
{
    protected ?string $heading = 'Ringkasan Keuangan';
    protected ?string $description = 'Ikhtisar akun utama untuk saldo kas, piutang, persediaan, dan hutang.';
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $rows = JurnalUmum::query()
            ->selectRaw('kode_akun, SUM(debit) as total_debit, SUM(kredit) as total_kredit')
            ->groupBy('kode_akun')
            ->get()
            ->keyBy('kode_akun');

        $format = fn ($angka) => 'Rp ' . number_format($angka, 0, ',', '.');

        $saldoKas = (float) (($rows[config('akun.kas')]->total_debit ?? 0) - ($rows[config('akun.kas')]->total_kredit ?? 0));
        $saldoPiutang = (float) (($rows[config('akun.piutang_usaha')]->total_debit ?? 0) - ($rows[config('akun.piutang_usaha')]->total_kredit ?? 0));
        $saldoPersediaan = (float) (($rows[config('akun.persediaan')]->total_debit ?? 0) - ($rows[config('akun.persediaan')]->total_kredit ?? 0));
        $saldoHutang = (float) (($rows[config('akun.hutang_usaha')]->total_kredit ?? 0) - ($rows[config('akun.hutang_usaha')]->total_debit ?? 0));

        return [
            Stat::make('Saldo Kas', $format($saldoKas))
                ->color($saldoKas >= 0 ? 'success' : 'danger')
                ->description('Saldo akun kas saat ini'),
            Stat::make('Piutang Usaha', $format($saldoPiutang))
                ->color($saldoPiutang >= 0 ? 'success' : 'warning')
                ->description('Total piutang usaha'),
            Stat::make('Persediaan', $format($saldoPersediaan))
                ->color($saldoPersediaan >= 0 ? 'primary' : 'danger')
                ->description('Nilai persediaan saat ini'),
            Stat::make('Hutang Usaha', $format($saldoHutang))
                ->color($saldoHutang >= 0 ? 'danger' : 'success')
                ->description('Total hutang usaha'),
        ];
    }
}
