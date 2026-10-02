<?php

namespace App\Filament\Pages;

use App\Models\BiayaOperasional;
use App\Models\JurnalUmum;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class LaporanLabaRugiGudang extends Page
{
    public static function canAccess(): bool
    {
        return auth('gudang')->user()?->canAksesKeuangan() ?? false;
    }

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';
    protected static ?string $navigationLabel = 'Laporan Laba Rugi';
    protected static ?string $title = 'Laporan Laba Rugi';
    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.laporan-laba-rugi-gudang';

    public ?string $tanggalAwal = null;
    public ?string $tanggalAkhir = null;

    public function mount(): void
    {
        $this->bulanIni();
    }

    public function bulanIni(): void
    {
        $this->tanggalAwal = now()->startOfMonth()->toDateString();
        $this->tanggalAkhir = now()->endOfMonth()->toDateString();
    }

    public function bulanLalu(): void
    {
        $this->tanggalAwal = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $this->tanggalAkhir = now()->subMonthNoOverflow()->endOfMonth()->toDateString();
    }

    protected function getViewData(): array
    {
        return ['laporan' => $this->hitungLaporan()];
    }

    private function hitungLaporan(): array
    {
        try {
            $awal = Carbon::parse($this->tanggalAwal)->toDateString();
            $akhir = Carbon::parse($this->tanggalAkhir)->toDateString();
        } catch (\Throwable) {
            $awal = now()->startOfMonth()->toDateString();
            $akhir = now()->endOfMonth()->toDateString();
        }

        $akun = config('akun');

        $rows = JurnalUmum::query()
            ->whereDate('tanggal', '>=', $awal)
            ->whereDate('tanggal', '<=', $akhir)
            ->whereIn('kode_akun', [$akun['penjualan'], $akun['hpp'], $akun['biaya_operasional']])
            ->selectRaw('kode_akun, SUM(debit) as total_debit, SUM(kredit) as total_kredit')
            ->groupBy('kode_akun')
            ->get()
            ->keyBy('kode_akun');

        $penjualan = (float) (($rows[$akun['penjualan']]->total_kredit ?? 0) - ($rows[$akun['penjualan']]->total_debit ?? 0));
        $hpp = (float) (($rows[$akun['hpp']]->total_debit ?? 0) - ($rows[$akun['hpp']]->total_kredit ?? 0));
        $totalBiaya = (float) (($rows[$akun['biaya_operasional']]->total_debit ?? 0) - ($rows[$akun['biaya_operasional']]->total_kredit ?? 0));

        // Rincian biaya per kategori (dari catatan Biaya Operasional).
        $perKategori = BiayaOperasional::query()
            ->whereDate('tanggal', '>=', $awal)
            ->whereDate('tanggal', '<=', $akhir)
            ->selectRaw('kategori, SUM(nominal) as total')
            ->groupBy('kategori')
            ->pluck('total', 'kategori')
            ->map(fn ($total) => (float) $total)
            ->sortDesc();

        $rincianBiaya = [];
        foreach ($perKategori as $kategori => $total) {
            $rincianBiaya[] = [
                'label' => config('kategori_biaya.' . $kategori, $kategori),
                'total' => $total,
            ];
        }

        // Kalau total di jurnal lebih besar dari jumlah rincian, tampilkan
        // selisihnya supaya total biaya tetap cocok dengan jurnal.
        $selisih = $totalBiaya - (float) $perKategori->sum();
        if (abs($selisih) >= 1) {
            $rincianBiaya[] = ['label' => 'Biaya lain (tanpa kategori)', 'total' => $selisih];
        }

        $labaKotor = $penjualan - $hpp;
        $labaBersih = $labaKotor - $totalBiaya;

        return [
            'awal' => $awal,
            'akhir' => $akhir,
            'penjualan' => $penjualan,
            'hpp' => $hpp,
            'laba_kotor' => $labaKotor,
            'rincian_biaya' => $rincianBiaya,
            'total_biaya' => $totalBiaya,
            'laba_bersih' => $labaBersih,
            'margin' => $penjualan > 0 ? ($labaBersih / $penjualan) * 100 : null,
            'hpp_kosong' => $penjualan > 0 && $hpp <= 0,
            'penjualan_kosong' => $penjualan <= 0,
        ];
    }
}
