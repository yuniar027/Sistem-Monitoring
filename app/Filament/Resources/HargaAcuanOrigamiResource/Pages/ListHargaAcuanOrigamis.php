<?php

namespace App\Filament\Resources\HargaAcuanOrigamiResource\Pages;

use App\Filament\Imports\HargaAcuanOrigamiImporter;
use App\Filament\Resources\HargaAcuanOrigamiResource;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListHargaAcuanOrigamis extends ListRecords
{
    protected static string $resource = HargaAcuanOrigamiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(HargaAcuanOrigamiImporter::class)
                ->label('Import Harga Acuan Origami')
                ->chunkSize(250),
        ];
    }
}
