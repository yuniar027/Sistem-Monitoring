<?php

namespace App\Filament\Resources\ResepPaketItemResource\Pages;

use App\Filament\Imports\ResepPaketItemImporter;
use App\Filament\Resources\ResepPaketItemResource;
use App\Models\BahanBaku;
use App\Models\ProdukMaster;
use App\Models\ResepPaketItem;
use App\Services\PemetaanBahanBakuService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ListResepPaketItems extends ListRecords
{
    protected static string $resource = ResepPaketItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importResepExcel')
                ->label('Import Resep BOM (Excel)')
                ->icon('heroicon-o-document-arrow-up')
                ->color('warning')
                ->form([
                    FileUpload::make('file')
                        ->label('File Excel (.xlsx)')
                        ->helperText('Kolom wajib (header persis): SKU, Nama Bahan Baku, Kuantitas per Paket. Satu SKU boleh muncul di beberapa baris (satu baris per bahan).')
                        ->required()
                        ->disk('local')
                        ->directory('temp-import-resep')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ]),
                ])
                ->action(function (array $data) {
                    $pemetaan = app(PemetaanBahanBakuService::class);

                    $path = Storage::disk('local')->path($data['file']);

                    try {
                        $spreadsheet = IOFactory::load($path);
                    } catch (\Throwable $e) {
                        Storage::disk('local')->delete($data['file']);

                        Notification::make()
                            ->title('File tidak bisa dibaca')
                            ->body('Pastikan file benar-benar format .xlsx (bukan .xls/.csv yang cuma di-rename), dan tidak corrupt.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $rows = $spreadsheet->getActiveSheet()->toArray();

                    if (empty($rows)) {
                        Storage::disk('local')->delete($data['file']);
                        Notification::make()->title('File kosong')->danger()->send();
                        return;
                    }

                    $normalisasi = fn ($h) => trim(str_replace('_', ' ', strtolower((string) $h)));
                    $header = array_map($normalisasi, $rows[0]);
                    $idxSku = array_search('sku', $header, true);
                    $idxNamaBahan = array_search('nama bahan baku', $header, true);
                    $idxKuantitas = array_search('kuantitas per paket', $header, true);

                    if ($idxSku === false || $idxNamaBahan === false || $idxKuantitas === false) {
                        Storage::disk('local')->delete($data['file']);

                        Notification::make()
                            ->title('Format kolom tidak sesuai')
                            ->body('Pastikan header kolom persis: SKU, Nama Bahan Baku, Kuantitas per Paket')
                            ->danger()
                            ->send();
                        return;
                    }

                    $berhasil = 0;
                    $gagalSku = [];
                    $gagalBahan = [];

                    for ($i = 1; $i < count($rows); $i++) {
                        $row = $rows[$i];
                        $sku = trim((string) ($row[$idxSku] ?? ''));
                        $namaBahan = trim((string) ($row[$idxNamaBahan] ?? ''));
                        $kuantitas = (int) ($row[$idxKuantitas] ?? 0);

                        if ($sku === '' || $namaBahan === '' || $kuantitas <= 0) {
                            continue;
                        }

                        if (! ProdukMaster::where('sku', $sku)->exists()) {
                            $gagalSku[] = $sku;
                            continue;
                        }

                        $hasil = $pemetaan->petakanSatu($namaBahan);

                        if ($hasil['metode'] === 'tidak_ditemukan' || ! $hasil['kode_bahan']) {
                            $gagalBahan[] = "{$sku} | {$namaBahan}";
                            continue;
                        }

                        $bahanBaku = BahanBaku::where('kode_bahan', $hasil['kode_bahan'])->first();

                        if (! $bahanBaku) {
                            $gagalBahan[] = "{$sku} | {$namaBahan}";
                            continue;
                        }

                        ResepPaketItem::updateOrCreate(
                            ['sku' => $sku, 'bahan_baku_id' => $bahanBaku->id],
                            ['kuantitas_per_paket' => $kuantitas],
                        );

                        $berhasil++;
                    }

                    Storage::disk('local')->delete($data['file']);

                    $body = "{$berhasil} baris resep berhasil disimpan.";

                    if (! empty($gagalSku)) {
                        $body .= ' SKU tidak ditemukan: ' . implode(', ', array_unique($gagalSku)) . '.';
                    }

                    if (! empty($gagalBahan)) {
                        $tampil = array_slice(array_unique($gagalBahan), 0, 10);
                        $body .= ' Bahan tidak cocok, perlu dicek manual (' . count($gagalBahan) . ' baris): ' . implode('; ', $tampil) . (count($gagalBahan) > 10 ? '; ...' : '') . '.';
                    }

                    Notification::make()
                        ->title('Import Resep BOM (Excel) selesai')
                        ->body($body)
                        ->success()
                        ->duration(10000)
                        ->send();
                }),

            ImportAction::make()
                ->importer(ResepPaketItemImporter::class)
                ->label('Import Resep Paket (CSV)')
                ->chunkSize(50),

            CreateAction::make(),
        ];
    }
}