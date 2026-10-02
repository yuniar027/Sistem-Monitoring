<?php

namespace App\Filament\Pages;

use App\Filament\Resources\StokBarangGudangResource;
use App\Filament\Resources\StokVariasiHarianResource;
use App\Models\StokBarangGudang;
use App\Models\StokVariasiGudang;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;

class RingkasanStokPage extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-queue-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Monitoring Stok Ringkas';

    protected static ?string $navigationLabel = 'Ringkasan Stok';

    protected static ?string $title = 'Ringkasan Stok';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.ringkasan-stok';

    public string $tanggal;

    public ?string $kategoriFilter = null;

    public string $search = '';

    public int $page = 1;

    public int $perPage = 10;

    public function mount(): void
    {
        $this->tanggal = today()->toDateString();
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    public function updatedKategoriFilter(): void
    {
        $this->page = 1;
    }

    public function updatedTanggal(): void
    {
        $this->page = 1;
    }

    public function updatedPerPage(): void
    {
        $this->page = 1;
    }

    public function kategoriOptions(): array
    {
        return [
            StokBarangGudang::KATEGORI_AWAN => 'Awan',
            StokBarangGudang::KATEGORI_ORIGAMI => 'Origami',
        ];
    }

    /**
     * Kelompokkan per seri (kategori + nama dasar) -- pola sama persis
     * dengan InputStokHarianGabungan::getKelompokList(), supaya satu
     * seri yang sama selalu kebaca identik di kedua halaman.
     */
    public function getKelompokList()
    {
        return StokBarangGudang::query()
            ->when(
                $this->kategoriFilter,
                fn ($q) => $q->where('kategori', $this->kategoriFilter)
            )
            ->when(
                $this->search,
                fn ($q) => $q->where('nama_barang', 'ilike', '%' . $this->search . '%')
            )
            ->orderBy('nama_barang')
            ->get()
            ->groupBy(fn (StokBarangGudang $b) => $b->kategori . '|' . $b->nama_dasar)
            ->map(function ($anggota) {
                $pertama = $anggota->first();

                $variasiList = StokVariasiGudang::where('kategori', $pertama->kategori)
                    ->where('nama_dasar', $pertama->nama_dasar)
                    ->orderBy('kode_variasi')
                    ->get();

                return [
                    'nama_dasar' => $pertama->nama_dasar,
                    'kategori' => $pertama->kategori,
                    'barang' => $anggota->values(),
                    'variasi' => $variasiList,
                ];
            })
            ->sortBy('nama_dasar')
            ->values();
    }

    public function getKelompokListPaginated(): LengthAwarePaginator
    {
        $semua = $this->getKelompokList();

        $totalHalaman = max(1, (int) ceil($semua->count() / $this->perPage));

        if ($this->page > $totalHalaman) {
            $this->page = $totalHalaman;
        }

        return new LengthAwarePaginator(
            $semua->forPage($this->page, $this->perPage)->values(),
            $semua->count(),
            $this->perPage,
            $this->page
        );
    }

    public function kategoriLabel(?string $kategori): string
    {
        return StokBarangGudangResource::kategoriOptions()[$kategori] ?? ($kategori ?? '-');
    }

    public function urlEditVariasi(int $variasiGudangId, string $tanggal): ?string
    {
        $harian = StokVariasiGudang::find($variasiGudangId)?->harianPadaTanggal($tanggal);

        return $harian
            ? StokVariasiHarianResource::getUrl('edit', ['record' => $harian->id])
            : null;
    }
}