<?php

namespace App\Filament\Resources\BahanBakuResource\Pages;

use App\Filament\Imports\BahanBakuImporter;
use App\Filament\Resources\BahanBakuResource;
use App\Models\BahanBaku;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ListBahanBakus extends ListRecords
{
    protected static string $resource = BahanBakuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importStokAmanBahanBaku')
                ->label('Import Stok Aman (Excel Gudang)')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->modalDescription(
                    'Untuk file dari admin gudang (kolom NAMA, VARIASI, STOK AMAN, bisa banyak sheet). ' .
                    'Kolom NAMA dicocokkan LANGSUNG ke nama_bahan (nama persis sama, bukan tebak-tebakan) ' .
                    'karena data ini memang untuk bahan_baku, bukan produk jadi. Yang tidak ketemu dilaporkan, tidak error.'
                )
                ->form([
                    FileUpload::make('file')
                        ->label('File Excel (.xlsx)')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->required()
                        ->disk('local')
                        ->directory('temp-imports')
                        ->helperText('Kolom wajib (baris header): NAMA, STOK AMAN. Urutan bebas.'),
                ])
                ->action(function (array $data) {
                    $this->prosesImportStokAmanBahanBaku($data['file']);
                }),

            ImportAction::make()
                ->importer(BahanBakuImporter::class)
                ->label('Import Bahan Baku')
                ->chunkSize(50),

            CreateAction::make(),
        ];
    }

    private function prosesImportStokAmanBahanBaku(string $filePath): void
    {
        $fullPath = Storage::disk('local')->path($filePath);

        try {
            $spreadsheetSumber = IOFactory::load($fullPath);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($filePath);

            Notification::make()
                ->title('File tidak bisa dibaca')
                ->body('Pastikan file benar-benar format .xlsx (bukan .xls/.csv yang cuma di-rename), dan tidak corrupt.')
                ->danger()
                ->send();

            return;
        }

        $sukses = 0;
        $gagal = [];
        $belumAdaData = 0;

        // Preload katalog bahan_baku, kunci pakai nama yang dinormalisasi
        // (uppercase, spasi berlebih dirapikan, trim) supaya perbedaan kecil
        // seperti spasi ganda atau kapitalisasi tidak bikin gagal cocok.
        $katalog = BahanBaku::all(['id', 'nama_bahan'])
            ->keyBy(fn (BahanBaku $b) => $this->normalisasiNama($b->nama_bahan));

        foreach ($spreadsheetSumber->getAllSheets() as $sheet) {
            $namaSheet = $sheet->getTitle();
            $semuaBaris = $sheet->toArray(null, true, true, false);

            if (empty($semuaBaris)) {
                continue;
            }

            // Cari baris header sebenarnya (tidak selalu baris 1)
            $indexHeader = null;
            $header = [];

            foreach (array_slice($semuaBaris, 0, 5, true) as $i => $barisCek) {
                $barisUpper = array_map(fn ($h) => strtoupper(trim((string) $h)), $barisCek);
                if (in_array('NAMA', $barisUpper, true)) {
                    $indexHeader = $i;
                    $header = $barisUpper;
                    break;
                }
            }

            if ($indexHeader === null) {
                continue;
            }

            $rows = array_slice($semuaBaris, $indexHeader + 1, null, true);

            $kolomNama = array_search('NAMA', $header, true);
            $kolomStok = array_search('STOK AMAN', $header, true);

            if ($kolomNama === false || $kolomStok === false) {
                continue;
            }

            foreach ($rows as $i => $row) {
                $nama = trim((string) ($row[$kolomNama] ?? ''));

                if ($nama === '') {
                    continue; // baris kosong/pembatas
                }

                $stokRaw = $row[$kolomStok] ?? null;
                $stokBersih = is_numeric($stokRaw) ? (int) $stokRaw : null;

                if ($stokBersih === null && $stokRaw !== null && trim((string) $stokRaw) !== '') {
                    preg_match('/\d+/', (string) $stokRaw, $m);
                    $stokBersih = isset($m[0]) ? (int) $m[0] : null;
                }

                if ($stokBersih === null) {
                    $belumAdaData++;
                    continue;
                }

                $kunci = $this->normalisasiNama($nama);
                $bahan = $katalog->get($kunci);

                if (! $bahan) {
                    $gagal[] = "[{$namaSheet}] '{$nama}' tidak ditemukan di katalog Bahan Baku.";
                    continue;
                }

                BahanBaku::where('id', $bahan->id)->update(['target_stok_minimum' => $stokBersih]);
                $sukses++;
            }
        }

        Storage::disk('local')->delete($filePath);

        $judul = "Import stok aman selesai: {$sukses} bahan baku berhasil diupdate" .
            (count($gagal) ? ', ' . count($gagal) . ' tidak ditemukan' : '');

        Notification::make()
            ->title($judul)
            ->body(
                (count($gagal) ? implode("\n", array_slice($gagal, 0, 10)) : '') .
                ($belumAdaData > 0 ? "\n{$belumAdaData} baris dilewati karena STOK AMAN masih kosong." : '')
            )
            ->color(count($gagal) ? 'warning' : 'success')
            ->duration(count($gagal) ? null : 6000)
            ->send();
    }

    private function normalisasiNama(string $nama): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim($nama)));
    }
}