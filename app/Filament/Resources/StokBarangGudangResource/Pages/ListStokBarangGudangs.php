<?php

namespace App\Filament\Resources\StokBarangGudangResource\Pages;

use App\Filament\Resources\StokBarangGudangResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStokBarangGudangs extends ListRecords
{
    protected static string $resource = StokBarangGudangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}