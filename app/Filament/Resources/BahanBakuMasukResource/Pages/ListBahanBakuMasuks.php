<?php

namespace App\Filament\Resources\BahanBakuMasukResource\Pages;

use App\Filament\Resources\BahanBakuMasukResource;
use App\Filament\Imports\BahanBakuMasukImporter;
use App\Models\BahanBaku;
use App\Models\SaranPemetaanBahanBaku;
use App\Services\BahanBakuMasukService;
use App\Services\PemetaanBahanBakuService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ListBahanBakuMasuks extends ListRecords
{
    protected static string $resource = BahanBakuMasukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importExcel')
                ->label('Import Invoice Pabrik (.xlsx)')
                ->icon('heroicon-o-document-arrow-up')
                ->form([
                    DatePicker::make('tanggal')
                        ->label('Tanggal Transaksi')
                        ->required()
                        ->default(now()),
                    TextInput::make('vendor')
                        ->label('Vendor / Pabrik')
                        ->required(),
                    FileUpload::make('file')
                        ->label('File Invoice')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->required()
                        ->disk('local')
                        ->directory('temp-imports')
                        ->helperText('Kolom yang dibaca dari file: Item, Qty, Unit Price, Amount (persis format invoice pabrik, tidak perlu diubah). Item yang tidak yakin ke-mapping otomatis akan disimpan untuk direview di halaman AI Mapping.'),
                ])
                ->action(function (array $data) {
                    $this->prosesImportExcel($data['file'], $data['tanggal'], $data['vendor']);
                }),

            ImportAction::make()
                ->label('Import (CSV)')
                ->importer(BahanBakuMasukImporter::class),

            CreateAction::make(),
        ];
    }

    private function prosesImportExcel(string $filePath, string $tanggal, string $vendor): void
    {
        $fullPath = Storage::disk('local')->path($filePath);

        $spreadsheet = IOFactory::load($fullPath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, false);

        $headerRaw = array_shift($rows);
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $headerRaw);

        $pemetaan = app(PemetaanBahanBakuService::class);

        $sukses = 0;
        $gagal = [];
        $perluReview = 0;

        foreach ($rows as $i => $row) {
            $baris = $i + 2;

            $data = array_combine($header, $row);
            $namaItem = trim((string) ($data['item'] ?? ''));
            $qty = $data['qty'] ?? null;
            $unitPrice = $data['unit price'] ?? null;
            $amount = $data['amount'] ?? null;

            // Lewati baris kosong, header ulang (invoice biasa ada beberapa halaman
            // dengan header berulang), atau baris ringkasan seperti "Sub Total"
            if ($namaItem === '' || $namaItem === 'Item' || ! is_numeric($qty) || ! is_numeric($unitPrice)) {
                continue;
            }

            try {
                $hasil = $pemetaan->petakanSatu($namaItem);
                $yakin = in_array($hasil['metode'], ['heuristik', 'ai']);

                if (! $yakin) {
                    SaranPemetaanBahanBaku::create([
                        'nama_item' => $hasil['nama_item'],
                        'kode_bahan_disarankan' => $hasil['kode_bahan'],
                        'nama_bahan' => $hasil['nama_bahan'],
                        'metode' => $hasil['metode'],
                        'catatan' => $hasil['skor_atau_alasan'] ?? null,
                    ]);

                    $perluReview++;
                    $gagal[] = "Baris {$baris}: '{$namaItem}' belum yakin ke-mapping, disimpan untuk review manual.";

                    continue;
                }

                $bahanBaku = BahanBaku::where('kode_bahan', $hasil['kode_bahan'])->first();

                if (! $bahanBaku) {
                    throw new \Exception("Kode bahan hasil mapping '{$hasil['kode_bahan']}' tidak ditemukan di katalog.");
                }

                $totalNominal = is_numeric($amount) ? (float) $amount : ((float) $qty * (float) $unitPrice);

                app(BahanBakuMasukService::class)->catatBahanBakuMasuk([
                    'bahan_baku_id' => $bahanBaku->id,
                    'tanggal' => $tanggal,
                    'vendor' => $vendor,
                    'kuantitas' => (float) $qty,
                    'harga_satuan' => (float) $unitPrice,
                    'biaya_kirim' => 0,
                    'total_nominal' => $totalNominal,
                ]);

                $sukses++;
            } catch (\Exception $e) {
                $gagal[] = "Baris {$baris}: " . $e->getMessage();
            }
        }

        Storage::disk('local')->delete($filePath);

        $judul = "Import selesai: {$sukses} baris berhasil";
        if ($perluReview > 0) {
            $judul .= ", {$perluReview} perlu review manual";
        }
        $gagalMurni = count($gagal) - $perluReview;
        if ($gagalMurni > 0) {
            $judul .= ", {$gagalMurni} gagal";
        }

        Notification::make()
            ->title($judul)
            ->body(count($gagal) ? implode("\n", array_slice($gagal, 0, 10)) : null)
            ->color(count($gagal) ? 'warning' : 'success')
            ->duration(count($gagal) ? null : 6000)
            ->send();
    }
}