<?php

namespace App\Filament\Resources\PerbandinganHargaStokMasukResource\Pages;

use App\Filament\Resources\PerbandinganHargaStokMasukResource;
use Filament\Resources\Pages\ListRecords;

class ListPerbandinganHargaStokMasuks extends ListRecords
{
    protected static string $resource = PerbandinganHargaStokMasukResource::class;

    protected function getHeaderActions(): array
    {
        // Read-only + aksi Terima/Tolak per baris — baris dibuat otomatis
        // oleh StokMasukService, tidak ada tombol Create.
        return [];
    }
}
