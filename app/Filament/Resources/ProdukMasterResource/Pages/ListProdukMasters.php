<?php

namespace App\Filament\Resources\ProdukMasterResource\Pages;

use App\Filament\Imports\ProdukMasterImporter;
use App\Filament\Resources\ProdukMasterResource;
use App\Models\ProdukMaster;
use App\Services\HeuristikProdukMasterService;
use App\Services\HppService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ListProdukMasters extends ListRecords
{
    protected static string $resource = ProdukMasterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('hitungHpp')
                    ->label('Hitung Ulang HPP')
                    ->icon('heroicon-o-calculator')
                    ->color('success')
                    ->action(function () {
                        $service = App::make(HppService::class);
                        $hasil = $service->updateHppSemuaProdukRakitan();
                        $jumlahBerhasil = count($hasil['berhasil']);
                        $jumlahDilewati = count($hasil['dilewati']);

                        Notification::make()
                            ->title('Perhitungan HPP selesai')
                            ->body("{$jumlahBerhasil} SKU berhasil diupdate. {$jumlahDilewati} SKU dilewati (resep atau data harga bahan baku belum lengkap).")
                            ->success()
                            ->send();
                    }),

                Action::make('importTargetStokMinimum')
                    ->label('Import Batas Stok Minimum (Excel)')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->modalDescription(
                        'File harus punya 2 kolom: sku dan target_stok_minimum (angka). ' .
                        'SKU yang tidak ditemukan di sistem akan dilewati dan dilaporkan, tidak menyebabkan error.'
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
                            ->helperText('Kolom wajib: sku, target_stok_minimum.'),
                    ])
                    ->action(function (array $data) {
                        $this->prosesImportTargetStokMinimum($data['file']);
                    }),

                Action::make('importStokAmanFuzzy')
                    ->label('Cocokkan & Buat Draft SKU (Excel)')
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
                    ->modalDescription(
                        'Untuk file dari admin gudang yang formatnya beda: kolom NAMA (nama tulisan tangan) + VARIASI + STOK AMAN, ' .
                        'BUKAN kolom sku. Sistem akan mencocokkan nama produk secara otomatis (fuzzy matching) dan menghasilkan FILE EXCEL BARU ' .
                        'untuk di-download — TIDAK langsung mengubah data. Kolom "sku" di file hasil sudah otomatis terisi untuk yang cocokan ' .
                        'skornya tinggi; yang meragukan dibiarkan kosong dengan 3 saran SKU di kolom sebelahnya untuk dipilih manual. ' .
                        'Setelah direview/diperbaiki, upload file hasil ini ke "Import Batas Stok Minimum (Excel)" untuk benar-benar menerapkannya.'
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
                            ->helperText('Kolom wajib (baris header): NAMA, VARIASI, STOK AMAN. Urutan kolom bebas.'),
                    ])
                    ->action(function (array $data) {
                        return $this->buatDraftPencocokanStokAman($data['file']);
                    }),

                ImportAction::make()
                    ->importer(ProdukMasterImporter::class)
                    ->label('Import Produk Master')
                    ->chunkSize(250),
            ])
                ->label('Actions')
                ->icon('heroicon-o-ellipsis-vertical')
                ->color('gray')
                ->button(),

            CreateAction::make(),
        ];
    }

    private function prosesImportTargetStokMinimum(string $filePath): void
    {
        $fullPath = Storage::disk('local')->path($filePath);

        try {
            $spreadsheet = IOFactory::load($fullPath);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($filePath);

            Notification::make()
                ->title('File tidak bisa dibaca')
                ->body('Pastikan file benar-benar format .xlsx (bukan .xls/.csv yang cuma di-rename), dan tidak corrupt.')
                ->danger()
                ->send();

            return;
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        // Normalisasi nama kolom: lowercase, spasi jadi underscore, trim
        $header = array_map(
            fn ($h) => str_replace(' ', '_', strtolower(trim((string) $h))),
            array_shift($rows)
        );

        $kolomSku = array_search('sku', $header, true);
        $kolomTarget = array_search('target_stok_minimum', $header, true);

        if ($kolomSku === false || $kolomTarget === false) {
            Storage::disk('local')->delete($filePath);

            Notification::make()
                ->title('Format file tidak sesuai')
                ->body('Kolom "sku" dan "target_stok_minimum" wajib ada di baris header.')
                ->danger()
                ->send();

            return;
        }

        $sukses = 0;
        $gagal = [];

        foreach ($rows as $i => $row) {
            $baris = $i + 2; // +2 karena baris 1 = header

            if (empty(array_filter($row, fn ($v) => $v !== null && $v !== ''))) {
                continue; // lewati baris kosong
            }

            $sku = trim((string) ($row[$kolomSku] ?? ''));
            $targetRaw = $row[$kolomTarget] ?? null;

            if ($sku === '') {
                $gagal[] = "Baris {$baris}: SKU kosong.";
                continue;
            }

            if (! is_numeric($targetRaw) || (int) $targetRaw < 0) {
                $gagal[] = "Baris {$baris}: target_stok_minimum harus angka >= 0 (SKU: {$sku}).";
                continue;
            }

            $produk = ProdukMaster::where('sku', $sku)->first();

            if (! $produk) {
                $gagal[] = "Baris {$baris}: SKU '{$sku}' tidak ditemukan di Produk Master.";
                continue;
            }

            $produk->update(['target_stok_minimum' => (int) $targetRaw]);
            $sukses++;
        }

        Storage::disk('local')->delete($filePath);

        $judul = "Import batas stok minimum selesai: {$sukses} SKU berhasil diupdate" .
            (count($gagal) ? ', ' . count($gagal) . ' baris gagal' : '');

        Notification::make()
            ->title($judul)
            ->body(count($gagal) ? implode("\n", array_slice($gagal, 0, 10)) : null)
            ->color(count($gagal) ? 'warning' : 'success')
            ->duration(count($gagal) ? null : 6000)
            ->send();
    }

    private const AMBANG_YAKIN_PRODUK = 0.5; // di bawah ini, kandidat tidak dianggap layak jadi saran sama sekali
    private const AMBANG_OTOMATIS_ISI = 0.8; // di atas ini, kolom sku otomatis diisi (masih boleh dikoreksi manual)

    private function buatDraftPencocokanStokAman(string $filePath)
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

            return null;
        }

        $heuristik = App::make(HeuristikProdukMasterService::class);
        $katalog = $heuristik->muatKatalog(); // dimuat SEKALI, dipakai untuk semua baris & semua sheet

        $hasilBaris = [];
        $belumAdaData = 0; // STOK AMAN kosong di Excel — bukan error, cuma belum diisi, tidak ikut masuk draft

        foreach ($spreadsheetSumber->getAllSheets() as $sheet) {
            $namaSheet = $sheet->getTitle();
            $semuaBaris = $sheet->toArray(null, true, true, false);

            if (empty($semuaBaris)) {
                continue;
            }

            // Cari baris header yang SEBENARNYA — tidak selalu baris 1 (kadang ada baris kosong/judul di atasnya).
            // Cek 5 baris pertama, cari yang mengandung "NAMA" di salah satu selnya.
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
                continue; // tidak ketemu baris header sama sekali di 5 baris pertama — lewati sheet ini
            }

            // Ambil semua baris SETELAH baris header (bukan array_shift, karena header belum tentu di baris 1)
            $rows = array_slice($semuaBaris, $indexHeader + 1, null, true);

            $kolomNama = array_search('NAMA', $header, true);
            $kolomVariasi = array_search('VARIASI', $header, true);
            $kolomStok = array_search('STOK AMAN', $header, true);

            if ($kolomNama === false || $kolomStok === false) {
                continue; // sheet ini tidak punya kolom yang diharapkan — lewati diam-diam
            }

            foreach ($rows as $row) {
                $nama = trim((string) ($row[$kolomNama] ?? ''));

                if ($nama === '') {
                    continue; // baris kosong/pembatas antar grup warna
                }

                $variasi = $kolomVariasi !== false ? trim((string) ($row[$kolomVariasi] ?? '')) : '';
                $stokRaw = $row[$kolomStok] ?? null;

                // Ambil angka dari teks seperti "60 PCS" -> 60. String kosong/null = belum diisi Mbak Via.
                $stokBersih = is_numeric($stokRaw) ? (int) $stokRaw : null;
                if ($stokBersih === null && $stokRaw !== null && trim((string) $stokRaw) !== '') {
                    preg_match('/\d+/', (string) $stokRaw, $m);
                    $stokBersih = isset($m[0]) ? (int) $m[0] : null;
                }

                if ($stokBersih === null) {
                    $belumAdaData++;
                    continue;
                }

                $namaPencarian = trim($nama . ' ' . $variasi);
                $kandidat = $heuristik->cariKandidat($namaPencarian, $katalog, 3);

                $skuOtomatis = (! empty($kandidat) && $kandidat[0]['skor'] >= self::AMBANG_OTOMATIS_ISI)
                    ? $kandidat[0]['sku']
                    : '';

                $formatSaran = fn (?array $k) => $k
                    ? "{$k['sku']} | {$k['nama_produk']} (skor " . round($k['skor'], 2) . ')'
                    : '';

                $hasilBaris[] = [
                    'sku' => $skuOtomatis,
                    'target_stok_minimum' => $stokBersih,
                    'sheet_asal' => $namaSheet,
                    'nama_gudang' => $nama,
                    'variasi_gudang' => $variasi,
                    'saran_1' => $formatSaran($kandidat[0] ?? null),
                    'saran_2' => $formatSaran($kandidat[1] ?? null),
                    'saran_3' => $formatSaran($kandidat[2] ?? null),
                ];
            }
        }

        Storage::disk('local')->delete($filePath);

        // Bangun file Excel hasil — kolom sku & target_stok_minimum sengaja di depan
        // dan namanya PERSIS sama dengan yang dibutuhkan tombol "Import Batas Stok Minimum",
        // supaya file ini bisa langsung diupload ulang ke sana setelah direview, tanpa ubah apa-apa lagi.
        $spreadsheetHasil = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheetHasil = $spreadsheetHasil->getActiveSheet();
        $sheetHasil->setTitle('Draft Pencocokan');

        $kolomHeader = ['sku', 'target_stok_minimum', 'sheet_asal', 'nama_gudang', 'variasi_gudang', 'saran_1', 'saran_2', 'saran_3'];
        $sheetHasil->fromArray($kolomHeader, null, 'A1');

        $baris = 2;
        foreach ($hasilBaris as $r) {
            $sheetHasil->fromArray(array_values($r), null, "A{$baris}");
            $baris++;
        }

        foreach (range('A', 'H') as $kolom) {
            $sheetHasil->getColumnDimension($kolom)->setAutoSize(true);
        }

        $namaFileOutput = 'draft-pencocokan-stok-aman-' . now()->format('Y-m-d-His') . '.xlsx';
        $pathOutput = storage_path('app/temp-imports/' . $namaFileOutput);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheetHasil);
        $writer->save($pathOutput);

        $tanpaSaran = count(array_filter($hasilBaris, fn ($r) => $r['sku'] === ''));

        Notification::make()
            ->title('Draft pencocokan siap diunduh')
            ->body(
                count($hasilBaris) . " baris diproses ({$tanpaSaran} perlu dipilih manual dari kolom saran). " .
                ($belumAdaData > 0 ? "{$belumAdaData} baris dilewati karena STOK AMAN masih kosong di file asli." : '')
            )
            ->info()
            ->send();

        return response()->download($pathOutput, $namaFileOutput)->deleteFileAfterSend(true);
    }
}