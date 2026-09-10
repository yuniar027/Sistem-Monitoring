<?php

namespace App\Filament\Pages;

use App\Models\StokAlokasiKhususHarian;
use App\Models\StokBarangGudang;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Auth;

class InputStokHarianGabungan extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static string|\UnitEnum|null $navigationGroup = 'Monitoring Stok Ringkas';
    protected static ?string $navigationLabel = 'Input Stok Harian';
    protected static ?string $title = 'Input Stok Harian';

    protected string $view = 'filament.pages.input-stok-harian-gabungan';

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

    public function isPabrik(): bool
    {
        return Auth::guard('gudang')->user()?->isPabrik() ?? false;
    }

    public function kategoriOptions(): array
    {
        return [
            StokBarangGudang::KATEGORI_AWAN => 'Awan',
            StokBarangGudang::KATEGORI_ORIGAMI => 'Origami',
        ];
    }

    /**
     * Kelompokkan barang berdasarkan nama dasar (tanpa tag "- UM" dan
     * tanpa akhiran BT/PD/PJ) + kategori. Barang tanpa varian tetap
     * masuk sebagai kelompok isi 1.
     */
    public function getKelompokList()
    {
        return StokBarangGudang::query()
            ->when($this->kategoriFilter, fn ($q) => $q->where('kategori', $this->kategoriFilter))
            ->when($this->search, fn ($q) => $q->where('nama_barang', 'ilike', '%' . $this->search . '%'))
            ->orderBy('nama_barang')
            ->get()
            ->groupBy(fn (StokBarangGudang $b) => $b->kategori . '|' . $b->nama_dasar)
            ->map(function ($anggota) {
                $pertama = $anggota->first();
                $topiPasangan = StokBarangGudang::cariTopiPasangan($pertama->nama_dasar, $pertama->kategori);

                return [
                    'nama_dasar' => $pertama->nama_dasar,
                    'kategori' => $pertama->kategori,
                    'anggota' => $anggota->values(),
                    'topi_pasangan_id' => $topiPasangan?->id,
                    'topi_pasangan_nama' => $topiPasangan?->nama_barang,
                ];
            })
            ->sortBy('nama_dasar')
            ->values();
    }

    /**
     * Versi terpaginasi dari getKelompokList() untuk ditampilkan di tabel.
     */
    public function getKelompokListPaginated(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $semua = $this->getKelompokList();
        $totalHalaman = max(1, (int) ceil($semua->count() / $this->perPage));

        if ($this->page > $totalHalaman) {
            $this->page = $totalHalaman;
        }

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $semua->forPage($this->page, $this->perPage)->values(),
            $semua->count(),
            $this->perPage,
            $this->page
        );
    }

    /**
     * Action kecil "Alokasi Khusus" per varian, dipasang di header
     * Section masing-masing varian di dalam modal Isi.
     */
    protected function buatAlokasiKhususAction(int $barangId, string $namaLabel): Action
    {
        $tanggal = $this->tanggal;
        $pabrik = $this->isPabrik();

        return Action::make("alokasi_{$barangId}_{$tanggal}")
            ->label('Alokasi Khusus')
            ->icon('heroicon-o-adjustments-horizontal')
            ->size('sm')
            ->color('gray')
            ->modalHeading("Alokasi Khusus: {$namaLabel}")
            ->modalSubmitAction($pabrik ? false : null)
            ->fillForm(function () use ($barangId, $tanggal) {
                $entries = StokAlokasiKhususHarian::query()
                    ->where('barang_gudang_id', $barangId)
                    ->whereDate('tanggal', $tanggal)
                    ->get(['kode_alokasi', 'kuantitas'])
                    ->map(fn ($e) => ['kode_alokasi' => $e->kode_alokasi, 'kuantitas' => (float) $e->kuantitas])
                    ->toArray();

                return ['alokasi' => $entries];
            })
            ->schema([
                Repeater::make('alokasi')
                    ->label('')
                    ->schema([
                        TextInput::make('kode_alokasi')
                            ->label('Kode Alokasi')
                            ->helperText('Contoh: K 3 SET, K 18, K 48')
                            ->required(),
                        TextInput::make('kuantitas')
                            ->numeric()
                            ->required(),
                    ])
                    ->columns(2)
                    ->addActionLabel('Tambah Alokasi')
                    ->disabled($pabrik)
                    ->dehydrated(! $pabrik),
            ])
            ->action(function (array $data) use ($barangId, $tanggal, $pabrik) {
                if ($pabrik) {
                    return;
                }

                StokAlokasiKhususHarian::query()
                    ->where('barang_gudang_id', $barangId)
                    ->whereDate('tanggal', $tanggal)
                    ->delete();

                foreach ($data['alokasi'] ?? [] as $row) {
                    if (empty($row['kode_alokasi'])) {
                        continue;
                    }

                    StokAlokasiKhususHarian::create([
                        'barang_gudang_id' => $barangId,
                        'tanggal' => $tanggal,
                        'kode_alokasi' => $row['kode_alokasi'],
                        'kuantitas' => (float) ($row['kuantitas'] ?? 0),
                    ]);
                }

                Notification::make()->title('Alokasi khusus disimpan')->success()->send();
            });
    }

    public function isiKelompokAction(): Action
    {
        $pabrik = $this->isPabrik();

        return Action::make('isiKelompok')
            ->label('Isi')
            ->modalHeading(fn (array $arguments) => 'Isi Stok: ' . ($arguments['nama_dasar'] ?? ''))
            ->modalSubmitActionLabel('Simpan')
            ->fillForm(function (array $arguments) {
                $tanggal = $this->tanggal;
                $barangIds = $arguments['barang_ids'] ?? [];
                $state = ['barang' => []];

                foreach ($barangIds as $id) {
                    $barang = StokBarangGudang::find($id);
                    $harian = $barang?->harianPadaTanggal($tanggal);
                    $state['barang'][$id] = [
                        'rak' => (float) ($harian->rak ?? 0),
                        'input' => (float) ($harian->input ?? 0),
                        'um_titip_pabrik' => (float) ($harian->um_titip_pabrik ?? 0),
                        'stok_mentah_umma' => (float) ($harian->stok_mentah_umma ?? 0),
                    ];
                }

                return $state;
            })
            ->schema(function (array $arguments) use ($pabrik) {
                $barangIds = $arguments['barang_ids'] ?? [];
                $schema = [];

                foreach ($barangIds as $id) {
                    $barang = StokBarangGudang::find($id);

                    if (! $barang) {
                        continue;
                    }

                    $labelSeksi = $barang->akhiran_varian
                        ?? (\Illuminate\Support\Str::startsWith(
                            \Illuminate\Support\Str::upper(StokBarangGudang::buangTagPabrikPublic($barang->nama_barang)),
                            'TOPI SET'
                        ) ? 'Topi' : $barang->nama_barang);

                    $schema[] = Section::make($labelSeksi)
                        ->headerActions([
                            $this->buatAlokasiKhususAction($id, $labelSeksi),
                        ])
                        ->schema([
                            TextInput::make("barang.{$id}.rak")
                                ->label('Rak')
                                ->numeric()
                                ->required()
                                ->disabled($pabrik)
                                ->dehydrated(),
                            TextInput::make("barang.{$id}.input")
                                ->label('Input')
                                ->numeric()
                                ->required()
                                ->disabled($pabrik)
                                ->dehydrated(),
                            TextInput::make("barang.{$id}.um_titip_pabrik")
                                ->label('UM Titip Pabrik')
                                ->numeric(),
                            TextInput::make("barang.{$id}.stok_mentah_umma")
                                ->label('Stok Keseluruhan')
                                ->numeric()
                                ->disabled($pabrik)
                                ->dehydrated(),
                        ])
                        ->columns(2);
                }

                return $schema;
            })
            ->action(function (array $data): void {
                $tanggal = $this->tanggal;
                $jumlahDisimpan = 0;

                foreach ($data['barang'] ?? [] as $barangId => $nilai) {
                    $barang = StokBarangGudang::find($barangId);
                    $harian = $barang?->harianPadaTanggal($tanggal);

                    if ($harian) {
                        $harian->update([
                            'rak' => $nilai['rak'],
                            'input' => $nilai['input'],
                            'um_titip_pabrik' => $nilai['um_titip_pabrik'],
                            'stok_mentah_umma' => $nilai['stok_mentah_umma'],
                        ]);
                        $jumlahDisimpan++;
                    }
                }

                Notification::make()
                    ->title("Stok berhasil disimpan ({$jumlahDisimpan} varian)")
                    ->success()
                    ->send();
            });
    }
}