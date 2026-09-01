<?php

namespace App\Filament\Resources\StokVariasiHarianResource\Pages;

use App\Filament\Resources\StokVariasiHarianResource;
use Filament\Resources\Pages\ListRecords;

class ListStokVariasiHarians extends ListRecords
{
    protected static string $resource = StokVariasiHarianResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}