<?php

namespace App\Filament\Imports;

use App\Models\HargaAcuanOrigami;
use App\Models\StokBarangGudang;
use App\Services\HargaAcuanOrigamiService;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Str;
use RuntimeException;

class HargaAcuanAwanImporter extends Importer
{
    protected static ?string $model = HargaAcuanOrigami::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('nama_barang')
                ->label('Nama Barang')
                ->requiredMapping()
                ->rules([
                    'required',
                    'string',
                ])
                ->example('SET TUPAI PJ'),

            ImportColumn::make('harga_acuan')
                ->label('Harga Acuan')
                ->requiredMapping()
                ->numeric()
                ->rules([
                    'required',
                    'numeric',
                    'min:0',
                ])
                ->example('95000'),

            ImportColumn::make('berlaku_mulai')
                ->label('Berlaku Mulai')
                ->rules([
                    'nullable',
                    'date',
                ]),

            ImportColumn::make('catatan')
                ->label('Catatan')
                ->rules([
                    'nullable',
                    'string',
                    'max:255',
                ]),
        ];
    }

    public function resolveRecord(): HargaAcuanOrigami
    {
        return new HargaAcuanOrigami();
    }

    public function saveRecord(): void
    {
        $namaBarang = $this->normalisasiNama(
            $this->record->nama_barang
        );

        $barang = StokBarangGudang::query()
            ->where('kategori', StokBarangGudang::KATEGORI_AWAN)
            ->get(['id', 'nama_barang'])
            ->filter(function (StokBarangGudang $item) use ($namaBarang) {
                return $this->normalisasiNama(
                    $item->nama_barang
                ) === $namaBarang;
            });

        if ($barang->count() === 0) {
            throw new RuntimeException(
                "Barang Awan tidak ditemukan: {$this->record->nama_barang}"
            );
        }

        if ($barang->count() > 1) {
            throw new RuntimeException(
                "Nama barang Awan tidak unik: {$this->record->nama_barang}"
            );
        }

        $barangGudang = $barang->first();

        $this->record = app(HargaAcuanOrigamiService::class)
            ->tetapkanHargaAktifUntukBarangGudang(
                barangGudangId: $barangGudang->id,
                hargaAcuan: (float) $this->record->harga_acuan,
                berlakuMulai: $this->record->berlaku_mulai
                    ?: now()->toDateString(),
                catatan: $this->record->catatan
                    ?: 'Import Harga Acuan Awan',
            );
    }

    private function normalisasiNama(?string $nama): string
    {
        $nama = trim((string) $nama);

        $nama = preg_replace(
            '/\s*-\s*UM$/i',
            '',
            $nama
        );

        return Str::upper(
            Str::squish($nama)
        );
    }

    public static function getCompletedNotificationBody(
        Import $import
    ): string {
        $body = 'Import Harga Acuan Awan selesai: '
            . number_format($import->successful_rows)
            . ' baris berhasil diproses.';

        $failedRowsCount = $import->getFailedRowsCount();

        if ($failedRowsCount) {
            $body .= ' '
                . number_format($failedRowsCount)
                . ' baris gagal. Periksa detail kegagalan import.';
        }

        return $body;
    }
}