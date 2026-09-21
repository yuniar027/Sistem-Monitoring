<?php

namespace App\Filament\Pages;

use App\Models\ProductionProcess;
use App\Models\ProductionProcessTarget;
use App\Models\StokAlokasiKhususHarian;
use App\Models\StokBarangGudang;
use App\Services\ProductionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

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

    protected function getHeaderActions(): array
    {
        return [
            $this->buatProduksiAction(),
        ];
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

    protected function buatProduksiAction(): Action
    {
        return Action::make('produksi')
            ->label('Produksi K')
            ->icon('heroicon-o-cog-6-tooth')
            ->modalHeading('Catat Produksi K')
            ->schema([
                DatePicker::make('tanggal')
                    ->label('Tanggal')
                    ->default(fn (): string => $this->tanggal)
                    ->required(),

                Select::make('barang_gudang_id')
                    ->label('Barang Source')
                    ->options(
                        fn (): array => StokBarangGudang::query()
                            ->orderBy('nama_barang')
                            ->pluck('nama_barang', 'id')
                            ->all()
                    )
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('production_process_id')
                    ->label('Proses K')
                    ->options(
                        fn (): array => ProductionProcess::query()
                            ->orderBy('kode_proses')
                            ->pluck('kode_proses', 'id')
                            ->all()
                    )
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(
                        fn (Set $set): mixed => $set(
                            'production_process_target_id',
                            null
                        )
                    )
                    ->required(),

                Select::make('production_process_target_id')
                    ->label('Target Variation')
                    ->options(function (Get $get): array {
                        $processId = $get('production_process_id');

                        if (! $processId) {
                            return [];
                        }

                        return ProductionProcessTarget::query()
                            ->where('production_process_id', $processId)
                            ->with('variasiGudang')
                            ->get()
                            ->mapWithKeys(
                                fn (ProductionProcessTarget $target): array => [
                                    $target->id => $target->variasiGudang?->kode_variasi
                                        ?? "Target #{$target->id}",
                                ]
                            )
                            ->all();
                    })
                    ->disabled(
                        fn (Get $get): bool => ! $get('production_process_id')
                    )
                    ->searchable()
                    ->required(),

                TextInput::make('source_quantity')
                    ->label('Jumlah Source')
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $target = ProductionProcessTarget::query()
                    ->whereKey((int) $data['production_process_target_id'])
                    ->where(
                        'production_process_id',
                        (int) $data['production_process_id']
                    )
                    ->first();

                if (! $target) {
                    throw ValidationException::withMessages([
                        'production_process_target_id' =>
                            'Target variasi tidak sesuai dengan proses K yang dipilih.',
                    ]);
                }

                app(ProductionService::class)->create(
                    $data['tanggal'],
                    (int) $data['barang_gudang_id'],
                    (int) $data['production_process_target_id'],
                    $data['source_quantity'],
                );

                Notification::make()
                    ->title('Produksi K berhasil dicatat')
                    ->success()
                    ->send();
            });
    }

    /**
     * Kelompokkan barang berdasarkan nama dasar (tanpa tag "- UM" dan
     * tanpa akhiran BT/PD/PJ) + kategori. Barang tanpa varian tetap
     * masuk sebagai kelompok isi 1.
     */
    public function getKelompokList()
    {
        return StokBarangGudang::query()
            ->when(
                $this->kategoriFilter,
                fn ($q) => $q->where(
                    'kategori',
                    $this->kategoriFilter
                )
            )
            ->when(
                $this->search,
                fn ($q) => $q->where(
                    'nama_barang',
                    'ilike',
                    '%' . $this->search . '%'
                )
            )
            ->orderBy('nama_barang')
            ->get()
            ->groupBy(
                fn (StokBarangGudang $b) =>
                    $b->kategori . '|' . $b->nama_dasar
            )
            ->map(function ($anggota) {
                $pertama = $anggota->first();

                $topiPasangan = StokBarangGudang::cariTopiPasangan(
                    $pertama->nama_dasar,
                    $pertama->kategori
                );

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

        $totalHalaman = max(
            1,
            (int) ceil($semua->count() / $this->perPage)
        );

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
    protected function buatAlokasiKhususAction(
        int $barangId,
        string $namaLabel
    ): Action {
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
                    ->map(
                        fn ($e) => [
                            'kode_alokasi' => $e->kode_alokasi,
                            'kuantitas' => (float) $e->kuantitas,
                        ]
                    )
                    ->toArray();

                return [
                    'alokasi' => $entries,
                ];
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
                            ->minValue(0)
                            ->required(),
                    ])
                    ->columns(2)
                    ->addActionLabel('Tambah Alokasi')
                    ->disabled($pabrik)
                    ->dehydrated(! $pabrik),
            ])
            ->action(function (array $data) use (
                $barangId,
                $tanggal,
                $pabrik
            ) {
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

                Notification::make()
                    ->title('Alokasi khusus disimpan')
                    ->success()
                    ->send();
            });
    }

    public function isiKelompokAction(): Action
    {
        $pabrik = $this->isPabrik();

        return Action::make('isiKelompok')
            ->label('Isi')
            ->modalHeading(
                fn (array $arguments) =>
                    'Isi Stok: ' . ($arguments['nama_dasar'] ?? '')
            )
            ->modalSubmitActionLabel('Simpan')
            ->fillForm(function (array $arguments) {
                $tanggal = $this->tanggal;
                $barangIds = $arguments['barang_ids'] ?? [];

                $state = [
                    'barang' => [],
                ];

                foreach ($barangIds as $id) {
                    $barang = StokBarangGudang::find($id);
                    $harian = $barang?->harianPadaTanggal($tanggal);

                    $rak = (float) ($harian->rak ?? 0);
                    $input = (float) ($harian->input ?? 0);

                    $state['barang'][$id] = [
                        'rak' => $rak,
                        'input' => $input,
                        'um_titip_pabrik' => (float) (
                            $harian->um_titip_pabrik ?? 0
                        ),

                        // STOK SIAP = RAK + INPUT
                        'stok_siap' => $rak + $input,
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
                        ?? (
                            \Illuminate\Support\Str::startsWith(
                                \Illuminate\Support\Str::upper(
                                    StokBarangGudang::buangTagPabrikPublic(
                                        $barang->nama_barang
                                    )
                                ),
                                'TOPI SET'
                            )
                                ? 'Topi'
                                : $barang->nama_barang
                        );

                    $schema[] = Section::make($labelSeksi)
                        ->headerActions([
                            $this->buatAlokasiKhususAction(
                                $id,
                                $labelSeksi
                            ),
                        ])
                        ->schema([
                            TextInput::make("barang.{$id}.rak")
                                ->label('Rak')
                                ->numeric()
                                ->required()
                                ->disabled($pabrik)
                                ->live()
                                ->afterStateUpdated(
                                    function (
                                        Set $set,
                                        Get $get
                                    ) use ($id): void {
                                        $rak = (float) (
                                            $get("barang.{$id}.rak") ?? 0
                                        );

                                        $input = (float) (
                                            $get("barang.{$id}.input") ?? 0
                                        );

                                        $set(
                                            "barang.{$id}.stok_siap",
                                            $rak + $input
                                        );
                                    }
                                )
                                ->dehydrated(),

                            TextInput::make("barang.{$id}.input")
                                ->label('Input')
                                ->numeric()
                                ->required()
                                ->disabled($pabrik)
                                ->live()
                                ->afterStateUpdated(
                                    function (
                                        Set $set,
                                        Get $get
                                    ) use ($id): void {
                                        $rak = (float) (
                                            $get("barang.{$id}.rak") ?? 0
                                        );

                                        $input = (float) (
                                            $get("barang.{$id}.input") ?? 0
                                        );

                                        $set(
                                            "barang.{$id}.stok_siap",
                                            $rak + $input
                                        );
                                    }
                                )
                                ->dehydrated(),

                            TextInput::make(
                                "barang.{$id}.um_titip_pabrik"
                            )
                                ->label('UM Titip Pabrik')
                                ->numeric(),

                            TextInput::make(
                                "barang.{$id}.stok_siap"
                            )
                                ->label('Stok Siap')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(false),
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
                            'um_titip_pabrik' =>
                                $nilai['um_titip_pabrik'],
                        ]);

                        $jumlahDisimpan++;
                    }
                }

                Notification::make()
                    ->title(
                        "Stok berhasil disimpan ({$jumlahDisimpan} varian)"
                    )
                    ->success()
                    ->send();
            });
    }
}