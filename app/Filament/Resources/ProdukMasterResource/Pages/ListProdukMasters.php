<?php

namespace App\Filament\Resources\ProdukMasterResource\Pages;

use App\Filament\Imports\ProdukMasterImporter;
use App\Filament\Resources\ProdukMasterResource;
use App\Models\ProdukMaster;
use App\Services\PemetaanKodeProdukService;
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

                Actiong::make('importTargetStokMinimum')
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
                        'BUKAN kolom sku. Sistem mencocokkan lewat kode tipe & warna produk (bukan tebak-tebak kemiripan nama) dan ' .
                        'menghasilkan FILE EXCEL BARU untuk di-download — TIDAK langsung mengubah data. Kolom "sku" otomatis terisi ' .
                        'kalau ketemu tepat 1 kecocokan; kalau ambigu/tidak dikenali/tidak ditemukan, dilaporkan di kolom "status" ' .
                        'untuk dicek manual. Setelah direview, upload file hasil ini ke "Import Batas Stok Minimum (Excel)" untuk menerapkannya.'
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

        $pemeta = App::make(PemetaanKodeProdukService::class);

        $hasilBaris = [];
        $belumAdaData = 0; // STOK AMAN kosong di Excel — bukan error, cuma belum diisi, tidak ikut masuk draft

        foreach ($spreadsheetSumber->getAllSheets() as $sheet) {
            $namaSheet = $sheet->getTitle();
            $semuaBaris = $sheet->toArray(null, true, true, false);

            if (empty($semuaBaris)) {
                continue;
            }

            // Cari baris header yang SEBENARNYA — tidak selalu baris 1 (kadang ada baris kosong/judul di atasnya).
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

                $kodeTipe = $pemeta->deteksiTipe($nama, $variasi);
                $kodeWarna = $pemeta->deteksiWarna($nama, $namaSheet);

                $sku = '';
                $status = '';

                if ($kodeTipe === null && $kodeWarna === null) {
                    $status = 'TIPE & WARNA tidak dikenali — cek manual';
                } elseif ($kodeTipe === null) {
                    $status = "Warna terdeteksi ({$kodeWarna}), TIPE tidak dikenali — cek manual";
                } elseif ($kodeWarna === null) {
                    $status = "Tipe terdeteksi ({$kodeTipe}), WARNA tidak dikenali/belum tervalidasi — cek manual";
                } else {
                    $ditemukan = $pemeta->cariSkuByKode($kodeTipe, $kodeWarna);

                    if (count($ditemukan) === 1) {
                        $sku = $ditemukan[0]['sku'];
                        $status = 'Otomatis — 1 kecocokan persis';
                    } elseif (count($ditemukan) > 1) {
                        $daftarSku = implode(', ', array_column($ditemukan, 'sku'));
                        $status = 'Ambigu, ' . count($ditemukan) . " kecocokan ({$daftarSku}) — pilih manual";
                    } else {
                        $status = "Tipe={$kodeTipe} Warna={$kodeWarna}, TIDAK ADA SKU cocok — kemungkinan produk belum terdaftar";
                    }
                }

                $hasilBaris[] = [
                    'sku' => $sku,
                    'target_stok_minimum' => $stokBersih,
                    'sheet_asal' => $namaSheet,
                    'nama_gudang' => $nama,
                    'variasi_gudang' => $variasi,
                    'tipe_terdeteksi' => $kodeTipe ?? '',
                    'warna_terdeteksi' => $kodeWarna ?? '',
                    'status' => $status,
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

        $kolomHeader = ['sku', 'target_stok_minimum', 'sheet_asal', 'nama_gudang', 'variasi_gudang', 'tipe_terdeteksi', 'warna_terdeteksi', 'status'];
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

        Storage::disk('local')->makeDirectory('temp-imports');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheetHasil);
        $writer->save($pathOutput);

        $otomatis = count(array_filter($hasilBaris, fn ($r) => $r['sku'] !== ''));
        $perluCek = count($hasilBaris) - $otomatis;

        Notification::make()
            ->title('Draft pencocokan siap diunduh')
            ->body(
                count($hasilBaris) . " baris diproses: {$otomatis} otomatis kecocokan persis, {$perluCek} perlu dicek manual (lihat kolom status). " .
                ($belumAdaData > 0 ? "{$belumAdaData} baris dilewati karena STOK AMAN masih kosong di file asli." : '')
            )
            ->info()
            ->send();

        return response()->download($pathOutput, $namaFileOutput)->deleteFileAfterSend(true);
    }
}