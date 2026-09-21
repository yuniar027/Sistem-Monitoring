<?php

namespace App\Filament\Imports;

use App\Models\HargaAcuanOrigami;
use App\Services\HargaAcuanOrigamiService;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class HargaAcuanOrigamiImporter extends Importer
{
    protected static ?string $model = HargaAcuanOrigami::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('sku')
                ->label('SKU (Kode Barang)')
                ->requiredMapping()
                ->rules(['required', 'string', 'exists:produk_master,sku'])
                ->example('SLST0180'),

            ImportColumn::make('harga_acuan')
                ->label('Harga Acuan')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'numeric', 'min:0'])
                ->example('75000'),

            // Kosongkan kalau seluruh isi file berlaku mulai hari ini.
            ImportColumn::make('berlaku_mulai')
                ->rules(['nullable', 'date']),

            ImportColumn::make('catatan')
                ->rules(['nullable', 'string', 'max:255']),
        ];
    }

    /**
     * Record sementara — hanya wadah untuk column mapping (sku, harga_acuan,
     * berlaku_mulai, catatan). TIDAK pernah disimpan langsung; lihat
     * saveRecord() di bawah.
     */
    public function resolveRecord(): HargaAcuanOrigami
    {
        return new HargaAcuanOrigami();
    }

    /**
     * Importer HANYA membaca baris, memvalidasi SKU & harga (lewat rules di
     * atas), lalu meneruskan data yang sudah valid ke
     * HargaAcuanOrigamiService::tetapkanHargaAktif() — satu-satunya tempat
     * yang boleh menentukan is_active/berlaku_mulai/berlaku_sampai dan
     * menonaktifkan Harga Acuan lama. Tidak ada logic itu yang diduplikasi
     * di sini. Service itu juga sudah idempotent, jadi import ulang file
     * yang sama otomatis aman (lihat docblock service).
     */
    public function saveRecord(): void
    {
        $this->record = app(HargaAcuanOrigamiService::class)->tetapkanHargaAktif(
            sku: $this->record->sku,
            hargaAcuan: (float) $this->record->harga_acuan,
            berlakuMulai: $this->record->berlaku_mulai ?: now()->toDateString(),
            catatan: $this->record->catatan ?: 'Import awal Harga Acuan Origami',
        );
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Import Harga Acuan Origami selesai: ' . number_format($import->successful_rows) . ' baris berhasil diproses.';

        $failedRowsCount = $import->getFailedRowsCount();

        if ($failedRowsCount) {
            $body .= ' ' . number_format($failedRowsCount) . ' baris gagal — kemungkinan SKU belum terdaftar di Produk Master.';
        }

        return $body;
    }
}
