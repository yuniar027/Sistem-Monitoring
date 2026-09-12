<?php

namespace App\Filament\Resources\StokMentahResource\Pages;

use App\Filament\Resources\StokMentahResource;
use Filament\Resources\Pages\ListRecords;

class ListStokMentahs extends ListRecords
{
    protected static string $resource = StokMentahResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}