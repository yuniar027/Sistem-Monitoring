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
                ->requiredMapping()
                ->rules(['required', 'string', 'exists:bahan_baku,kode_bahan'])
                // kode_bahan bukan kolom di tabel bahan_baku_masuk, jadi jangan
                // di-set otomatis oleh Filament — resolveRecord() yang urus lookup-nya.
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
        // Setiap baris = 1 transaksi pembelian baru, bukan upsert
        // (beda dengan BahanBakuImporter yang pakai firstOrNew untuk master data)
        $bahanBaku = BahanBaku::where('kode_bahan', $this->data['kode_bahan'])->first();

        $record = new BahanBakuMasuk();
        $record->bahan_baku_id = $bahanBaku->id;

        return $record;
    }

    protected function beforeSave(): void
    {
        // Kalau kolom total_nominal kosong di file Excel, hitung otomatis
        if (empty($this->record->total_nominal)) {
            $this->record->total_nominal =
                ((float) $this->record->kuantitas * (float) $this->record->harga_satuan)
                + (float) ($this->record->biaya_kirim ?? 0);
        }
    }

    protected function afterSave(): void
    {
        // Record sudah tersimpan lewat proses save standar Filament (sekali, tanpa duplikasi).
        // Di sini cuma jalankan efek samping: update stok + jurnal — method yang sama
        // persis dipakai form Create manual, supaya hasilnya konsisten.
        app(BahanBakuMasukService::class)->prosesEfekBahanBakuMasuk($this->record);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Import bahan baku masuk selesai: ' . number_format($import->successful_rows) . ' baris berhasil diproses.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' baris gagal — lihat notifikasi kegagalan untuk detail.';
        }

        return $body;
    }
}