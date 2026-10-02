<?php

namespace App\Filament\Resources\BiayaOperasionals\Pages;

use App\Filament\Resources\BiayaOperasionals\BiayaOperasionalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBiayaOperasionals extends ListRecords
{
    protected static string $resource = BiayaOperasionalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Biaya'),
        ];
    }
}
