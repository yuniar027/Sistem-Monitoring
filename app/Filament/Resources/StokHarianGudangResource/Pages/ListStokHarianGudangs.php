<?php

namespace App\Filament\Resources\StokHarianGudangResource\Pages;

use App\Filament\Resources\StokHarianGudangResource;
use Filament\Resources\Pages\ListRecords;

class ListStokHarianGudangs extends ListRecords
{
    protected static string $resource = StokHarianGudangResource::class;

    protected function getHeaderActions(): array
    {
        // Tidak ada tombol Create - baris digenerate otomatis oleh
        // scheduler `stok:generate-harian`, bukan diinput manual.
        return [];
    }
}