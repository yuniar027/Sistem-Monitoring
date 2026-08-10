<?php

namespace App\Filament\Resources\ProdukMasterResource\Pages;

use App\Filament\Imports\ProdukMasterImporter;
use App\Filament\Resources\ProdukMasterResource;
use App\Models\ProdukMaster;
use App\Services\HppService;
use Filament\Actions\Action;
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

            ImportAction::make()
                ->importer(ProdukMasterImporter::class)
                ->label('Import Produk Master')
                ->chunkSize(250),

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
}