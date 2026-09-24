<?php

namespace App\Filament\Widgets;

use App\Models\PembelianGudangDetail;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class TrenHargaWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Tren Kenaikan & Penurunan Harga per Bulan';

    protected ?string $maxHeight = '320px';

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * Jumlah baris invoice per bulan yang kategorinya "naik" / "turun"
     * (dibanding Harga Acuan yang berlaku saat invoice itu dicatat).
     * "sama" tidak dihitung karena bukan pergerakan harga.
     */
    protected function getData(): array
    {
        $kategori = $this->pageFilters['kategori'] ?? null;
        $tanggalAwal = $this->pageFilters['tanggal_awal'] ?? null;
        $tanggalAkhir = $this->pageFilters['tanggal_akhir'] ?? null;

        $rows = PembelianGudangDetail::query()
            ->whereIn('kategori_perbandingan', ['naik', 'turun'])
            ->join(
                'pembelian_gudang',
                'pembelian_gudang.id',
                '=',
                'pembelian_gudang_detail.pembelian_gudang_id'
            )
            ->when(
                $tanggalAwal,
                fn ($q) => $q->whereDate(
                    'pembelian_gudang.tanggal',
                    '>=',
                    $tanggalAwal
                )
            )
            ->when(
                $tanggalAkhir,
                fn ($q) => $q->whereDate(
                    'pembelian_gudang.tanggal',
                    '<=',
                    $tanggalAkhir
                )
            )
            ->when($kategori, function ($q) use ($kategori): void {
                $q->whereExists(function ($sub) use ($kategori): void {
                    $sub->selectRaw('1')
                        ->from('stok_barang_gudang')
                        ->whereColumn(
                            'stok_barang_gudang.id',
                            'pembelian_gudang_detail.barang_gudang_id'
                        )
                        ->where('stok_barang_gudang.kategori', $kategori);
                });
            })
            ->selectRaw(
                "to_char(pembelian_gudang.tanggal, 'YYYY-MM') as bulan, "
                . 'kategori_perbandingan, COUNT(*) as jumlah'
            )
            ->groupBy('bulan', 'kategori_perbandingan')
            ->orderBy('bulan')
            ->get();

        $bulanList = $rows->pluck('bulan')->unique()->sort()->values();

        $cariJumlah = fn (string $bulan, string $tipe): int => (int) (
            $rows->first(
                fn ($r) => $r->bulan === $bulan
                    && $r->kategori_perbandingan === $tipe
            )?->jumlah ?? 0
        );

        return [
            'datasets' => [
                [
                    'label' => 'Naik',
                    'data' => $bulanList
                        ->map(fn ($b) => $cariJumlah($b, 'naik'))
                        ->all(),
                    'backgroundColor' => '#ef4444',
                ],
                [
                    'label' => 'Turun',
                    'data' => $bulanList
                        ->map(fn ($b) => $cariJumlah($b, 'turun'))
                        ->all(),
                    'backgroundColor' => '#22c55e',
                ],
            ],
            'labels' => $bulanList->all(),
        ];
    }
}