<?php

namespace App\Filament\Widgets;

use App\Models\PembelianGudangDetail;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RingkasanHargaWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Ringkasan Perbandingan Harga';

    protected function query(): \Illuminate\Database\Eloquent\Builder
    {
        $kategori = $this->pageFilters['kategori'] ?? null;
        $tanggalAwal = $this->pageFilters['tanggal_awal'] ?? null;
        $tanggalAkhir = $this->pageFilters['tanggal_akhir'] ?? null;

        return PembelianGudangDetail::query()
            ->whereNotNull('kategori_perbandingan')
            ->whereHas(
                'pembelian',
                function ($query) use ($tanggalAwal, $tanggalAkhir): void {
                    if ($tanggalAwal) {
                        $query->whereDate('tanggal', '>=', $tanggalAwal);
                    }

                    if ($tanggalAkhir) {
                        $query->whereDate('tanggal', '<=', $tanggalAkhir);
                    }
                }
            )
            ->when(
                $kategori,
                fn ($query) => $query->whereHas(
                    'barangGudang',
                    fn ($q2) => $q2->where('kategori', $kategori)
                )
            );
    }

    protected function getStats(): array
    {
        $rows = $this->query()->get();

        $naik = $rows->where('kategori_perbandingan', 'naik');
        $turun = $rows->where('kategori_perbandingan', 'turun');

        $formatPersen = fn (?float $n): string => $n === null
            ? '—'
            : number_format(abs($n), 1) . '%';

        $formatRupiah = fn (float $n): string => 'Rp ' . number_format(
            $n,
            0,
            ',',
            '.'
        );

        $totalSelisih = (float) $rows->sum('selisih_nominal');

        return [
            Stat::make('Barang Naik Harga', $naik->count())
                ->description(
                    $naik->isNotEmpty()
                        ? 'Rata-rata naik ' . $formatPersen(
                            $naik->avg('persen_selisih')
                        )
                        : 'Tidak ada kenaikan'
                )
                ->color('danger'),

            Stat::make('Barang Turun Harga', $turun->count())
                ->description(
                    $turun->isNotEmpty()
                        ? 'Rata-rata turun ' . $formatPersen(
                            $turun->avg('persen_selisih')
                        )
                        : 'Tidak ada penurunan'
                )
                ->color('success'),

            Stat::make(
                'Kenaikan Tertinggi',
                $naik->isNotEmpty()
                    ? $formatPersen($naik->max('persen_selisih'))
                    : '—'
            )->color('danger'),

            Stat::make(
                'Total Selisih Nominal',
                $formatRupiah($totalSelisih)
            )
                ->description(
                    $totalSelisih >= 0
                        ? 'Invoice lebih mahal dari acuan'
                        : 'Invoice lebih murah dari acuan'
                )
                ->color($totalSelisih >= 0 ? 'danger' : 'success'),
        ];
    }
}