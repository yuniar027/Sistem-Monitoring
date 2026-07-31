<?php

namespace App\Filament\Imports;

use App\Models\BahanBaku;
use App\Models\BahanBakuMasuk;
use App\Services\BahanBakuMasukService;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class BahanBakuMasukImporter extends Importer
{
    protected static ?string $model = BahanBakuMasuk::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('kode_bahan')
                ->label('Kode Bahan (kode resmi pabrik)')
                ->requiredMapping()
                ->rules(['required', 'string', 'exists:bahan_baku,kode_bahan'])
                ->example('SLBD0160')
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('tanggal')
                ->requiredMapping()
                ->rules(['required', 'date']),

            ImportColumn::make('vendor')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),

            ImportColumn::make('kuantitas')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer', 'min:1']),

            ImportColumn::make('harga_satuan')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'numeric', 'min:0']),

            ImportColumn::make('biaya_kirim')
                ->numeric()
                ->rules(['nullable', 'numeric', 'min:0']),

            ImportColumn::make('total_nominal')
                ->numeric()
                ->rules(['nullable', 'numeric', 'min:0']),
        ];
    }

    public function resolveRecord(): BahanBakuMasuk
    {
        $bahanBaku = BahanBaku::where('kode_bahan', $this->data['kode_bahan'])->first();

        $record = new BahanBakuMasuk();
        $record->bahan_baku_id = $bahanBaku->id;

        return $record;
    }

    protected function beforeSave(): void
    {
        if (empty($this->record->total_nominal)) {
            $this->record->total_nominal =
                ((float) $this->record->kuantitas * (float) $this->record->harga_satuan)
                + (float) ($this->record->biaya_kirim ?? 0);
        }
    }

    protected function afterSave(): void
    {
        app(BahanBakuMasukService::class)->prosesEfekBahanBakuMasuk($this->record);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Import bahan baku masuk selesai: ' . number_format($import->successful_rows) . ' baris berhasil diproses.';

        $failedRowsCount = $import->getFailedRowsCount();

        if ($failedRowsCount) {
            $body .= ' ' . number_format($failedRowsCount) . ' baris gagal.';

            // Kalau semua/hampir semua baris gagal, kemungkinan besar file yang diupload
            // salah — bukan file kode bahan resmi, tapi nama item mentah dari invoice.
            if ($failedRowsCount >= $import->total_rows * 0.5) {
                $body .= ' Catatan: kalau kode bahan yang gagal terlihat seperti nama item '
                    . '(bukan kode resmi seperti SLBD0160), kemungkinan file ini adalah invoice mentah. '
                    . 'Gunakan halaman "Import Transaksi Harian" untuk file jenis itu, bukan tombol ini.';
            }
        }

        return $body;
    }
}