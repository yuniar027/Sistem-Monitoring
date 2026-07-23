<?php

namespace App\Filament\Imports;

use App\Models\BahanBaku;
use App\Models\ResepPaketItem;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class ResepPaketItemImporter extends Importer
{
    protected static ?string $model = ResepPaketItem::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('sku')
                ->requiredMapping()
                ->rules(['required', 'string', 'exists:produk_master,sku']),

            ImportColumn::make('kode_bahan')
                ->requiredMapping()
                ->rules(['required', 'string'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('kuantitas_per_paket')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer', 'min:1']),
        ];
    }

    public function resolveRecord(): ResepPaketItem
    {
        $bahanBaku = BahanBaku::where('kode_bahan', $this->data['kode_bahan'])->first();

        if (! $bahanBaku) {
            throw new RowImportFailedException("kode_bahan '{$this->data['kode_bahan']}' tidak ditemukan di Bahan Baku.");
        }

        return ResepPaketItem::firstOrNew([
            'sku' => $this->data['sku'],
            'bahan_baku_id' => $bahanBaku->id,
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Import resep paket (BOM) selesai: ' . number_format($import->successful_rows) . ' baris berhasil diproses.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' baris gagal — lihat notifikasi kegagalan untuk detail.';
        }

        return $body;
    }
}
