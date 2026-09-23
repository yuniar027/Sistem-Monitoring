<?php

namespace App\Filament\Pages;

use App\Models\ProductionEvent;
use App\Models\ProductionProcess;
use App\Models\ProductionProcessTarget;
use App\Models\StokAlokasiKhususHarian;
use App\Models\StokBarangGudang;
use App\Models\StokVariasiGudang;
use App\Services\ProductionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
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
use Illuminate\Support\Facades\DB;

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
     * Field "Konsumsi" gabungan untuk satu barang: tiap baris dipilih
     * adminnya sendiri jadi "Proses K Terdaftar" (bikin ProductionEvent,
     * otomatis nambah produksi_input variasi target) atau "Alokasi
     * Manual" (bikin StokAlokasiKhususHarian, kode bebas). Dropdown
     * tipe wajib dipilih manual sesuai kesepakatan -- tidak ditebak
     * otomatis dari kode yang diketik.
     */
    protected function konsumsiField(int $barangId, ?string $kategori): Repeater
    {
        return Repeater::make("konsumsi.{$barangId}")
            ->label('Konsumsi')
            ->addActionLabel('Tambah Konsumsi')
            ->itemLabel(function (?array $state): ?string {
                return match ($state['tipe'] ?? null) {
                    'proses_k' => 'Proses K',
                    'manual' => ! empty($state['kode_alokasi'])
                        ? $state['kode_alokasi']
                        : 'Alokasi Manual',
                    default => null,
                };
            })
            ->defaultItems(0)
            ->schema([
                Select::make('tipe')
                    ->label('Tipe')
                    ->options([
                        'proses_k' => 'Proses K Terdaftar',
                        'manual' => 'Alokasi Manual',
                    ])
                    ->native(false)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($set): void {
                        $set('production_process_id', null);
                        $set('production_process_target_id', null);
                        $set('source_quantity', null);
                        $set('kode_alokasi', null);
                        $set('kuantitas', null);
                    }),

                Select::make('production_process_id')
                    ->label('Proses K')
                    ->options(
                        fn (): array => ProductionProcess::query()
                            ->when(
                                $kategori,
                                fn ($q) => $q->whereHas(
                                    'targets.variasiGudang',
                                    fn ($q2) => $q2->where(
                                        'kategori',
                                        $kategori
                                    )
                                )
                            )
                            ->orderBy('kode_proses')
                            ->pluck('kode_proses', 'id')
                            ->all()
                    )
                    ->searchable()
                    ->preload()
                    ->live()
                    ->visible(
                        fn ($get): bool => $get('tipe') === 'proses_k'
                    )
                    ->required(
                        fn ($get): bool => $get('tipe') === 'proses_k'
                    )
                    ->afterStateUpdated(
                        fn ($set): mixed => $set(
                            'production_process_target_id',
                            null
                        )
                    ),

                Select::make('production_process_target_id')
                    ->label('Target Variasi')
                    ->options(function ($get): array {
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
                    ->searchable()
                    ->visible(
                        fn ($get): bool => $get('tipe') === 'proses_k'
                    )
                    ->disabled(
                        fn ($get): bool => ! $get('production_process_id')
                    )
                    ->required(
                        fn ($get): bool => $get('tipe') === 'proses_k'
                    ),

                TextInput::make('source_quantity')
                    ->label('Jumlah Source')
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->visible(
                        fn ($get): bool => $get('tipe') === 'proses_k'
                    )
                    ->required(
                        fn ($get): bool => $get('tipe') === 'proses_k'
                    ),

                TextInput::make('kode_alokasi')
                    ->label('Kode Alokasi')
                    ->helperText('Contoh: K 3 SET, K 18, K 48')
                    ->visible(
                        fn ($get): bool => $get('tipe') === 'manual'
                    )
                    ->required(
                        fn ($get): bool => $get('tipe') === 'manual'
                    ),

                TextInput::make('kuantitas')
                    ->numeric()
                    ->minValue(0)
                    ->visible(
                        fn ($get): bool => $get('tipe') === 'manual'
                    )
                    ->required(
                        fn ($get): bool => $get('tipe') === 'manual'
                    ),
            ])
            ->columns(2);
    }

    public function isiKelompokAction(): Action
    {
        $pabrik = $this->isPabrik();

        return Action::make('isiKelompok')
            ->label('Isi')
            ->modalWidth('4xl')
            ->modalHeading(
                fn (array $arguments) =>
                    'Isi Stok: ' . ($arguments['nama_dasar'] ?? '')
            )
            ->modalSubmitActionLabel('Simpan')
            ->modalSubmitAction($pabrik ? false : null)
            ->fillForm(function (array $arguments) {
                $tanggal = $this->tanggal;
                $barangIds = $arguments['barang_ids'] ?? [];
                $kategori = $arguments['kategori'] ?? null;
                $namaDasar = $arguments['nama_dasar'] ?? null;

                $state = [
                    'barang' => [],
                    'konsumsi' => [],
                    'variasi' => [],
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

                    $konsumsi = [];

                    foreach (
                        StokAlokasiKhususHarian::query()
                            ->where('barang_gudang_id', $id)
                            ->whereDate('tanggal', $tanggal)
                            ->get()
                        as $alokasi
                    ) {
                        $konsumsi[] = [
                            'tipe' => 'manual',
                            'kode_alokasi' => $alokasi->kode_alokasi,
                            'kuantitas' => (float) $alokasi->kuantitas,
                        ];
                    }

                    foreach (
                        ProductionEvent::query()
                            ->where('barang_gudang_id', $id)
                            ->whereDate('tanggal', $tanggal)
                            ->with('productionProcessTarget')
                            ->get()
                        as $event
                    ) {
                        $konsumsi[] = [
                            'tipe' => 'proses_k',
                            'production_process_id' => $event
                                ->productionProcessTarget
                                ?->production_process_id,
                            'production_process_target_id' =>
                                $event->production_process_target_id,
                            'source_quantity' => (float) $event->source_quantity,
                        ];
                    }

                    $state['konsumsi'][$id] = $konsumsi;
                }

                if ($kategori && $namaDasar) {
                    foreach (
                        StokVariasiGudang::where('kategori', $kategori)
                            ->where('nama_dasar', $namaDasar)
                            ->orderBy('kode_variasi')
                            ->get() as $variasi
                    ) {
                        $vHarian = $variasi->harianPadaTanggal($tanggal);

                        $state['variasi'][$variasi->id] = [
                            'stok_awal' => (float) ($vHarian->stok_awal ?? 0),
                            'input' => (float) ($vHarian->input ?? 0),
                            'produksi_input' => (float) ($vHarian->produksi_input ?? 0),
                            'out' => (float) ($vHarian->out ?? 0),
                        ];
                    }
                }

                return $state;
            })
            ->schema(function (array $arguments) use ($pabrik) {
                $barangIds = $arguments['barang_ids'] ?? [];
                $kategori = $arguments['kategori'] ?? null;
                $namaDasar = $arguments['nama_dasar'] ?? null;

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

                            $this->konsumsiField($id, $kategori)
                                ->disabled($pabrik)
                                ->dehydrated(! $pabrik)
                                ->columnSpanFull(),
                        ])
                        ->columns(2);
                }

                if ($kategori && $namaDasar) {
                    $variasiList = StokVariasiGudang::where('kategori', $kategori)
                        ->where('nama_dasar', $namaDasar)
                        ->orderBy('kode_variasi')
                        ->get();

                    if ($variasiList->isNotEmpty()) {
                        $schema[] = Section::make('Variasi')
                            ->columnSpanFull()
                            ->schema(
                                $variasiList->map(function (StokVariasiGudang $variasi) use ($pabrik) {
                                    $id = $variasi->id;

                                    return Section::make($variasi->kode_variasi)
                                        ->schema([
                                            TextInput::make("variasi.{$id}.stok_awal")
                                                ->label('Stok Awal')
                                                ->numeric()
                                                ->disabled()
                                                ->dehydrated(false),

                                            TextInput::make("variasi.{$id}.produksi_input")
                                                ->label('Input Produksi K')
                                                ->helperText('Otomatis dari Konsumsi bertipe Proses K di atas, terisi setelah disimpan.')
                                                ->numeric()
                                                ->disabled()
                                                ->dehydrated(false),

                                            TextInput::make("variasi.{$id}.input")
                                                ->label('Input Manual')
                                                ->numeric()
                                                ->disabled($pabrik)
                                                ->dehydrated(! $pabrik),

                                            TextInput::make("variasi.{$id}.out")
                                                ->label('Out')
                                                ->numeric()
                                                ->disabled($pabrik)
                                                ->dehydrated(! $pabrik),
                                        ])
                                        ->columns(4);
                                })->all()
                            );
                    }
                }

                return $schema;
            })
            ->action(function (array $data) use ($pabrik): void {
                if ($pabrik) {
                    return;
                }

                $tanggal = $this->tanggal;
                $jumlahDisimpan = 0;

                DB::transaction(function () use ($data, $tanggal, &$jumlahDisimpan): void {

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

                    // Hapus per-record (bukan bulk delete) supaya event
                    // model (ripple stok, sinkron produksi_input) tetap
                    // jalan, lalu bangun ulang dari baris Konsumsi yang
                    // dikirim admin.
                    StokAlokasiKhususHarian::query()
                        ->where('barang_gudang_id', $barangId)
                        ->whereDate('tanggal', $tanggal)
                        ->get()
                        ->each
                        ->delete();

                    ProductionEvent::query()
                        ->where('barang_gudang_id', $barangId)
                        ->whereDate('tanggal', $tanggal)
                        ->get()
                        ->each
                        ->delete();

                    foreach (
                        $data['konsumsi'][$barangId] ?? []
                        as $row
                    ) {
                        if (($row['tipe'] ?? null) === 'manual') {
                            if (empty($row['kode_alokasi'])) {
                                continue;
                            }

                            StokAlokasiKhususHarian::create([
                                'barang_gudang_id' => $barangId,
                                'tanggal' => $tanggal,
                                'kode_alokasi' => $row['kode_alokasi'],
                                'kuantitas' => (float) (
                                    $row['kuantitas'] ?? 0
                                ),
                            ]);

                            continue;
                        }

                        if (($row['tipe'] ?? null) === 'proses_k') {
                            if (
                                empty($row['production_process_target_id'])
                                || empty($row['source_quantity'])
                            ) {
                                continue;
                            }

                            $target = ProductionProcessTarget::query()
                                ->whereKey(
                                    (int) $row['production_process_target_id']
                                )
                                ->where(
                                    'production_process_id',
                                    (int) ($row['production_process_id'] ?? 0)
                                )
                                ->first();

                            if (! $target) {
                                continue;
                            }

                            app(ProductionService::class)->create(
                                $tanggal,
                                (int) $barangId,
                                $target->id,
                                $row['source_quantity'],
                            );
                        }
                    }
                }

                foreach ($data['variasi'] ?? [] as $variasiId => $nilai) {
                    $variasi = StokVariasiGudang::find($variasiId);
                    $harianVariasi = $variasi?->harianPadaTanggal($tanggal);

                    if ($harianVariasi) {
                        $harianVariasi->update([
                            'input' => $nilai['input'] ?? $harianVariasi->input,
                            'out' => $nilai['out'] ?? $harianVariasi->out,
                        ]);
                    }
                }

                });

                Notification::make()
                    ->title(
                        "Stok berhasil disimpan ({$jumlahDisimpan} varian)"
                    )
                    ->success()
                    ->send();
            });
    }
}