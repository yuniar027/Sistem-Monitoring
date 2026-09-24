<?php

namespace App\Filament\Resources\HargaAcuanOrigamis\Pages;

use App\Filament\Resources\HargaAcuanOrigamis\HargaAcuanOrigamiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHargaAcuanOrigamis extends ListRecords
{
    protected static string $resource = HargaAcuanOrigamiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}