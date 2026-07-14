<?php

namespace App\Filament\Resources\StokMasukResource\Pages;

use App\Filament\Resources\StokMasukResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;

class ListStokMasuks extends ListRecords
{
    protected static string $resource = StokMasukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
