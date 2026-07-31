<?php

namespace App\Filament\Resources\BahanBakuMasukResource\Pages;

use App\Filament\Resources\BahanBakuMasukResource;
use App\Filament\Imports\BahanBakuMasukImporter;
use App\Models\BahanBaku;
use App\Services\BahanBakuMasukService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ListBahanBakuMasuks extends ListRecords
{
    protected static string $resource = BahanBakuMasukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importExcel')
                ->label('Import Excel (.xlsx)')
                ->icon('heroicon-o-document-arrow-up')
                ->modalDescription(
                    'Kolom kode_bahan HARUS berisi kode resmi dari katalog pabrik (contoh: SLBD0160), '
                    . 'bukan nama item dari invoice mentah (contoh: "ST CLN PDK COKLAT"). '
                    . 'Untuk invoice mentah yang belum ada kodenya, gunakan halaman "Import Transaksi Harian" di menu Transaksi.'
                )
                ->form([
                    FileUpload::make('file')
                        ->label('File Excel')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->required()
                        ->disk('local')
                        ->directory('temp-imports')
                        ->helperText('Kolom wajib: kode_bahan (kode resmi, contoh SLBD0160), tanggal, vendor, kuantitas, harga_satuan. Kolom biaya_kirim dan total_nominal boleh dikosongkan (dihitung otomatis).'),
                ])
                ->action(function (array $data) {
                    $this->prosesImportExcel($data['file']);
                }),

            ImportAction::make()
                ->label('Import (CSV)')
                ->importer(BahanBakuMasukImporter::class)
                ->modalDescription(
                    'Untuk kasus khusus (misal migrasi data lama). Kolom kode_bahan tetap harus '
                    . 'berisi kode resmi dari katalog pabrik, sama seperti Import Excel.'
                ),

            CreateAction::make(),
        ];
    }

    private function prosesImportExcel(string $filePath): void
    {
        $fullPath = Storage::disk('local')->path($filePath);

        $spreadsheet = IOFactory::load($fullPath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        $header = array_map(
            fn ($h) => strtolower(trim((string) $h)),
            array_shift($rows)
        );

        $sukses = 0;
        $gagal = [];
        $totalBarisData = 0;

        foreach ($rows as $i => $row) {
            $baris = $i + 2; // +2 karena baris 1 = header, index mulai dari 0

            if (empty(array_filter($row, fn ($v) => $v !== null && $v !== ''))) {
                continue; // lewati baris kosong
            }

            $totalBarisData++;
            $data = array_combine($header, $row);

            try {
                $kodeBahan = trim((string) ($data['kode_bahan'] ?? ''));
                $bahanBaku = BahanBaku::where('kode_bahan', $kodeBahan)->first();

                if (! $bahanBaku) {
                    throw new \Exception("Kode bahan '{$kodeBahan}' tidak ditemukan di katalog.");
                }

                $tanggalRaw = $data['tanggal'] ?? null;
                $tanggal = is_numeric($tanggalRaw)
                    ? ExcelDate::excelToDateTimeObject($tanggalRaw)->format('Y-m-d')
                    : Carbon::parse($tanggalRaw)->format('Y-m-d');

                $kuantitas = (float) ($data['kuantitas'] ?? 0);
                $hargaSatuan = (float) ($data['harga_satuan'] ?? 0);
                $biayaKirim = (float) ($data['biaya_kirim'] ?? 0);
                $totalNominal = ! empty($data['total_nominal'])
                    ? (float) $data['total_nominal']
                    : ($kuantitas * $hargaSatuan) + $biayaKirim;

                app(BahanBakuMasukService::class)->catatBahanBakuMasuk([
                    'bahan_baku_id' => $bahanBaku->id,
                    'tanggal' => $tanggal,
                    'vendor' => trim((string) ($data['vendor'] ?? '-')),
                    'kuantitas' => $kuantitas,
                    'harga_satuan' => $hargaSatuan,
                    'biaya_kirim' => $biayaKirim,
                    'total_nominal' => $totalNominal,
                ]);

                $sukses++;
            } catch (\Exception $e) {
                $gagal[] = "Baris {$baris}: " . $e->getMessage();
            }
        }

        Storage::disk('local')->delete($filePath);

        $judul = "Import selesai: {$sukses} baris berhasil" . (count($gagal) ? ', ' . count($gagal) . ' gagal' : '');
        $body = count($gagal) ? implode("\n", array_slice($gagal, 0, 10)) : null;

        // Kalau mayoritas baris gagal karena kode tidak ditemukan, kemungkinan besar
        // file yang diupload adalah invoice mentah (nama item), bukan kode resmi.
        if ($totalBarisData > 0 && count($gagal) >= $totalBarisData * 0.5) {
            $body .= "\n\nCatatan: sebagian besar baris gagal karena kode bahan tidak ditemukan. "
                . 'Kalau kode bahan yang gagal terlihat seperti nama item (bukan kode resmi seperti SLBD0160), '
                . 'file ini kemungkinan adalah invoice mentah dari pabrik yang belum di-mapping. '
                . 'Gunakan halaman "Import Transaksi Harian" di menu Transaksi untuk file jenis itu.';
        }

        Notification::make()
            ->title($judul)
            ->body($body)
            ->color(count($gagal) ? 'warning' : 'success')
            ->duration(count($gagal) ? null : 6000)
            ->send();
    }
}