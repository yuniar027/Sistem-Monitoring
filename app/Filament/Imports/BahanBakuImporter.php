<?php

namespace App\Filament\Imports;

use App\Models\BahanBaku;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class BahanBakuImporter extends Importer
{
    protected static ?string $model = BahanBaku::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('kode_bahan')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),

            ImportColumn::make('nama_bahan')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:500']),

            ImportColumn::make('satuan_beli')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:50']),

            ImportColumn::make('isi_per_satuan_beli')
                ->numeric()
                ->rules(['nullable', 'integer', 'min:1']),
        ];
    }

    public function resolveRecord(): BahanBaku
    {
        return BahanBaku::firstOrNew([
            'kode_bahan' => $this->data['kode_bahan'],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Import bahan baku selesai: ' . number_format($import->successful_rows) . ' baris berhasil diproses.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' baris gagal — lihat notifikasi kegagalan untuk detail.';
        }

        return $body;
    }
}
