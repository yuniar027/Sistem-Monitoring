<?php

namespace App\Filament\Imports;

use App\Models\ProdukMaster;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class ProdukMasterImporter extends Importer
{
    protected static ?string $model = ProdukMaster::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('sku')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),

            ImportColumn::make('nama_produk')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:500']),

            ImportColumn::make('satuan_jual')
                ->rules(['nullable', 'string', 'max:50']),

            ImportColumn::make('satuan_beli')
                ->rules(['nullable', 'string', 'max:50']),

            ImportColumn::make('isi_per_satuan_beli')
                ->numeric()
                ->rules(['nullable', 'integer', 'min:1']),

            ImportColumn::make('kategori')
                ->rules(['nullable', 'string', 'max:255']),

            ImportColumn::make('harga_modal_default')
                ->numeric()
                ->rules(['nullable', 'numeric', 'min:0']),

            ImportColumn::make('harga_jual_referensi')
                ->numeric()
                ->rules(['nullable', 'numeric', 'min:0']),

            ImportColumn::make('target_stok_minimum')
                ->numeric()
                ->rules(['nullable', 'integer', 'min:0']),

            ImportColumn::make('tipe_produk')
                ->rules(['nullable', 'in:simple,rakitan']),
        ];
    }

    /**
     * Ini kunci dari "updateOrCreate berdasarkan SKU, hindari duplikat"
     * yang diminta di roadmap Sprint 3: kalau SKU sudah ada, Filament
     * akan update baris itu, bukan bikin baris baru.
     */
    public function resolveRecord(): ProdukMaster
    {
        return ProdukMaster::firstOrNew([
            'sku' => $this->data['sku'],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Import produk master selesai: ' . number_format($import->successful_rows) . ' baris berhasil diproses.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' baris gagal — lihat notifikasi kegagalan untuk detail.';
        }

        return $body;
    }
}
