<?php

namespace App\Filament\Pages;

use App\Models\JurnalUmum;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class NeracaPage extends Page
{
    protected static ?string $navigationLabel = 'Neraca';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-scale';
    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';
    protected static ?string $title = 'Neraca';

    protected string $view = 'filament.pages.neraca';

    public ?string $tanggal = null;

    public function mount(): void
    {
        $this->tanggal = Carbon::now()->toDateString();
    }

    public function getViewData(): array
    {
        $rows = JurnalUmum::query()
            ->selectRaw('kode_akun, SUM(debit) as total_debit, SUM(kredit) as total_kredit')
            ->groupBy('kode_akun')
            ->get()
            ->keyBy('kode_akun');

        $saldoAset = [
            '1100' => ($rows['1100']->total_debit ?? 0) - ($rows['1100']->total_kredit ?? 0),
            '1200' => ($rows['1200']->total_debit ?? 0) - ($rows['1200']->total_kredit ?? 0),
            '1300' => ($rows['1300']->total_debit ?? 0) - ($rows['1300']->total_kredit ?? 0),
        ];

        $pendapatan = ($rows['4100']->total_kredit ?? 0) - ($rows['4100']->total_debit ?? 0);
        $hpp = ($rows['5100']->total_debit ?? 0) - ($rows['5100']->total_kredit ?? 0);
        $biayaOperasional = ($rows['6100']->total_debit ?? 0) - ($rows['6100']->total_kredit ?? 0);
        $labaDitahan = $pendapatan - $hpp - $biayaOperasional;

        $saldoKewajibanModal = [
            '2100' => ($rows['2100']->total_kredit ?? 0) - ($rows['2100']->total_debit ?? 0),
            '3100' => ($rows['3100']->total_kredit ?? 0) - ($rows['3100']->total_debit ?? 0),
            'laba_ditahan' => $labaDitahan,
        ];

        $totalAset = array_sum($saldoAset);
        $totalKewajibanModal = array_sum($saldoKewajibanModal);
        $selisih = $totalAset - $totalKewajibanModal;

        return [
            'saldoAset' => $saldoAset,
            'saldoKewajibanModal' => $saldoKewajibanModal,
            'totalAset' => $totalAset,
            'totalKewajibanModal' => $totalKewajibanModal,
            'selisih' => $selisih,
        ];
    }
}