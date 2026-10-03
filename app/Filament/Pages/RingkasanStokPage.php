<?php

namespace App\Filament\Pages;

use App\Filament\Resources\StokBarangGudangResource;
use App\Filament\Resources\StokVariasiHarianResource;
use App\Models\ProductionEvent;
use App\Models\StokAlokasiKhususHarian;
use App\Models\StokBarangGudang;
use App\Models\StokHarianGudang;
use App\Models\StokVariasiGudang;
use App\Models\StokVariasiHarian;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

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
        $barangSemua = StokBarangGudang::query()
            ->when(
                $this->kategoriFilter,
                fn ($q) => $q->where('kategori', $this->kategoriFilter)
            )
            ->when(
                $this->search,
                fn ($q) => $q->where('nama_barang', 'ilike', '%' . $this->search . '%')
            )
            ->orderBy('nama_barang')
            ->get();

        // Satu query untuk semua variasi, bukan satu query per seri.
        $variasiPerSeri = StokVariasiGudang::query()
            ->whereIn('kategori', $barangSemua->pluck('kategori')->unique()->values())
            ->orderBy('kode_variasi')
            ->get()
            ->groupBy(fn (StokVariasiGudang $v) => $v->kategori . '|' . $v->nama_dasar);

        return $barangSemua
            ->groupBy(fn (StokBarangGudang $b) => $b->kategori . '|' . $b->nama_dasar)
            ->map(function ($anggota) use ($variasiPerSeri) {
                $pertama = $anggota->first();

                $variasiList = $variasiPerSeri->get($pertama->kategori . '|' . $pertama->nama_dasar, collect());

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

    /**
     * Data harian untuk satu halaman, dimuat dengan beberapa query sekaligus
     * (bukan per baris). Hasilnya dipakai blade lewat dataHalaman().
     *
     * @return array{harian: array, stokAkhir: array, variasiHarian: array, urlEdit: array}
     */
    public function dataHalaman($daftar): array
    {
        $barangIds = [];
        $variasiIds = [];

        foreach ($daftar as $kelompok) {
            foreach ($kelompok['barang'] as $b) {
                $barangIds[] = $b->id;
            }
            foreach ($kelompok['variasi'] as $v) {
                $variasiIds[] = $v->id;
            }
        }

        $harian = StokHarianGudang::query()
            ->whereIn('barang_gudang_id', $barangIds)
            ->whereDate('tanggal', $this->tanggal)
            ->get()
            ->unique('barang_gudang_id')
            ->keyBy('barang_gudang_id');

        $alokasi = StokAlokasiKhususHarian::query()
            ->whereIn('barang_gudang_id', $barangIds)
            ->whereDate('tanggal', $this->tanggal)
            ->selectRaw('barang_gudang_id, sum(kuantitas) as total')
            ->groupBy('barang_gudang_id')
            ->pluck('total', 'barang_gudang_id');

        $produksi = ProductionEvent::query()
            ->whereIn('barang_gudang_id', $barangIds)
            ->whereDate('tanggal', $this->tanggal)
            ->selectRaw('barang_gudang_id, sum(source_quantity) as total')
            ->groupBy('barang_gudang_id')
            ->pluck('total', 'barang_gudang_id');

        // Rumus sama dengan accessor stok_akhir: stok siap - alokasi khusus - konsumsi produksi.
        $stokAkhir = [];
        foreach ($harian as $barangId => $h) {
            $stokAkhir[$barangId] = (float) $h->rak + (float) $h->input
                - (float) ($alokasi[$barangId] ?? 0)
                - (float) ($produksi[$barangId] ?? 0);
        }

        $variasiHarian = StokVariasiHarian::query()
            ->whereIn('variasi_gudang_id', $variasiIds)
            ->whereDate('tanggal', $this->tanggal)
            ->get()
            ->unique('variasi_gudang_id')
            ->keyBy('variasi_gudang_id');

        $pabrik = Auth::guard('gudang')->user()?->isPabrik() ?? false;
        $urlEdit = [];
        foreach ($variasiHarian as $variasiId => $vh) {
            $urlEdit[$variasiId] = $pabrik
                ? null
                : StokVariasiHarianResource::getUrl('edit', ['record' => $vh->id]);
        }

        return [
            'harian' => $harian->all(),
            'stokAkhir' => $stokAkhir,
            'variasiHarian' => $variasiHarian->all(),
            'urlEdit' => $urlEdit,
        ];
    }

    public function kategoriLabel(?string $kategori): string
    {
        return StokBarangGudangResource::kategoriOptions()[$kategori] ?? ($kategori ?? '-');
    }

    public function urlEditVariasi(int $variasiGudangId, string $tanggal): ?string
    {
        if (\Illuminate\Support\Facades\Auth::guard('gudang')->user()?->isPabrik()) {
            return null;
        }

        $harian = StokVariasiGudang::find($variasiGudangId)?->harianPadaTanggal($tanggal);

        return $harian
            ? StokVariasiHarianResource::getUrl('edit', ['record' => $harian->id])
            : null;
    }
}