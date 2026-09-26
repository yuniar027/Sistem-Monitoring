<?php

namespace App\Filament\Pages;

use App\Models\StokBarangGudang;
use App\Models\StokHarianGudang;
use App\Services\SuratJalanPdfParser;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ImportSuratJalanGudang extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|\UnitEnum|null $navigationGroup = 'Monitoring Stok Ringkas';

    protected static ?string $navigationLabel = 'Import Surat Jalan';

    protected static ?string $title = 'Import Surat Jalan Gudang';

    protected string $view = 'filament.pages.import-surat-jalan-gudang';

    public ?array $data = [];

    /**
     * @var array<int, array{
     *   kode: string, nama: string, nama_master: ?string, qty: float,
     *   cocok: bool, barang_gudang_id: ?int, input_lama: ?float
     * }>|null
     */
    public ?array $preview = null;

    public ?string $tanggalPreview = null;

    /** @var array<int, string> */
    public array $peringatan = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                FileUpload::make('files')
                    ->label('PDF Surat Jalan')
                    ->multiple()
                    ->acceptedFileTypes(['application/pdf'])
                    ->disk('local')
                    ->directory('imports/surat-jalan')
                    ->visibility('private')
                    ->required()
                    ->maxSize(20480)
                    ->helperText('Boleh upload beberapa PDF sekaligus (misal Gudang 1 + Gudang 2 untuk tanggal yang sama) -- kuantitas digabung otomatis per kode barang.'),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('preview')
                ->label('Import & Preview')
                ->submit('bacaPdf')
                ->color('primary'),
        ];
    }

    public function bacaPdf(SuratJalanPdfParser $parser): void
    {
        $state = $this->form->getState();
        $files = $state['files'] ?? [];

        if (empty($files)) {
            Notification::make()->title('Belum ada file PDF yang diupload')->danger()->send();

            return;
        }

        // kode_barang => ['nama' => ..., 'qty' => total gabungan]
        $gabungan = [];
        $peringatan = [];
        $tanggalTerdeteksi = [];

        foreach ($files as $path) {
            $absolut = Storage::disk('local')->path($path);
            $namaFile = basename($path);
            $hasil = $parser->parse($absolut);

            if ($hasil['tanggal']) {
                $tanggalTerdeteksi[] = $hasil['tanggal'];
            }

            if (! $hasil['cocok']) {
                $peringatan[] = sprintf(
                    'File %s: total terbaca (%s) BEDA sama TOTAL di footer PDF-nya (%s) -- cek manual dulu sebelum disimpan.',
                    $namaFile,
                    number_format($hasil['total_terbaca'], 2, ',', '.'),
                    number_format((float) $hasil['total_footer'], 2, ',', '.')
                );
            }

            if (empty($hasil['items'])) {
                $peringatan[] = "File {$namaFile}: tidak ada satu baris barang pun yang berhasil dibaca.";
            }

            if (! empty($hasil['item_tanpa_qty'])) {
                $peringatan[] = "File {$namaFile}: ada " . count($hasil['item_tanpa_qty']) . ' nama barang yang gak ketemu pasangan angkanya, dilewati.';
            }

            foreach ($hasil['items'] as $item) {
                $kode = $item['kode'];

                if (! isset($gabungan[$kode])) {
                    $gabungan[$kode] = [
                        'nama' => $item['nama'],
                        'qty' => 0.0,
                    ];
                }

                $gabungan[$kode]['qty'] += $item['qty'];
            }
        }

        $tanggalUnik = array_values(array_unique($tanggalTerdeteksi));

        if (count($tanggalUnik) > 1) {
            $peringatan[] = 'File yang diupload punya tanggal berbeda-beda (' . implode(', ', $tanggalUnik) . '). Sistem pakai tanggal pertama yang ketemu -- cek ulang kalau salah.';
        }

        $tanggal = $tanggalUnik[0] ?? today()->toDateString();

        $barangByKode = StokBarangGudang::query()
            ->whereIn('kode_barang', array_keys($gabungan))
            ->get()
            ->keyBy('kode_barang');

        $preview = [];

        foreach ($gabungan as $kode => $baris) {
            $barang = $barangByKode->get($kode);
            $existing = $barang?->harianPadaTanggal($tanggal);

            $preview[] = [
                'kode' => $kode,
                'nama' => $baris['nama'],
                'nama_master' => $barang?->nama_barang,
                'qty' => round($baris['qty'], 2),
                'cocok' => (bool) $barang,
                'barang_gudang_id' => $barang?->id,
                'input_lama' => $existing ? (float) $existing->input : null,
            ];
        }

        usort($preview, function ($a, $b) {
            if ($a['cocok'] !== $b['cocok']) {
                return $a['cocok'] ? 1 : -1; // yang gak cocok ditaruh di atas biar kelihatan
            }

            return strcmp($a['kode'], $b['kode']);
        });

        $this->preview = $preview;
        $this->tanggalPreview = $tanggal;
        $this->peringatan = $peringatan;

        $jumlahCocok = count(array_filter($preview, fn ($p) => $p['cocok']));
        $jumlahGakCocok = count($preview) - $jumlahCocok;

        Notification::make()
            ->title(
                "Selesai dibaca untuk tanggal {$tanggal}: {$jumlahCocok} kode cocok"
                . ($jumlahGakCocok > 0 ? ", {$jumlahGakCocok} kode TIDAK ketemu di Master Barang Gudang" : '')
            )
            ->color($jumlahGakCocok > 0 ? 'warning' : 'success')
            ->send();
    }

    public function simpanKeStokHarian(): void
    {
        if (empty($this->preview) || ! $this->tanggalPreview) {
            Notification::make()->title('Belum ada data preview untuk disimpan')->danger()->send();

            return;
        }

        $tanggal = $this->tanggalPreview;
        $kemarin = Carbon::parse($tanggal)->subDay()->toDateString();

        $disimpan = 0;
        $dilewati = 0;

        foreach ($this->preview as $baris) {
            if (! $baris['cocok'] || ! $baris['barang_gudang_id']) {
                $dilewati++;

                continue;
            }

            $harian = StokHarianGudang::where('barang_gudang_id', $baris['barang_gudang_id'])
                ->whereDate('tanggal', $tanggal)
                ->first();

            if ($harian) {
                $harian->update(['input' => $baris['qty']]);
            } else {
                // Belum ada snapshot untuk tanggal ini -- bikin baru,
                // rak dilanjutkan dari stok_akhir hari sebelumnya
                // (pola sama persis dengan stok:generate-harian).
                $harianKemarin = StokHarianGudang::where('barang_gudang_id', $baris['barang_gudang_id'])
                    ->whereDate('tanggal', $kemarin)
                    ->first();

                StokHarianGudang::create([
                    'barang_gudang_id' => $baris['barang_gudang_id'],
                    'tanggal' => $tanggal,
                    'rak' => $harianKemarin?->stok_akhir ?? 0,
                    'input' => $baris['qty'],
                ]);
            }

            $disimpan++;
        }

        Notification::make()
            ->title("Berhasil disimpan ke Input Stok Harian tanggal {$tanggal}")
            ->body("{$disimpan} kode barang ter-update." . ($dilewati > 0 ? " {$dilewati} kode dilewati karena gak cocok ke Master Barang Gudang." : ''))
            ->success()
            ->send();

        $this->preview = null;
        $this->tanggalPreview = null;
        $this->peringatan = [];
        $this->form->fill();
    }

    public function batalkanPreview(): void
    {
        $this->preview = null;
        $this->tanggalPreview = null;
        $this->peringatan = [];
    }
}