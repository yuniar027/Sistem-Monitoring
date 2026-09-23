<?php

namespace App\Filament\Resources\HargaAcuanOrigamiResource\Pages;

use App\Filament\Imports\HargaAcuanAwanImporter;
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
            ImportAction::make('importHargaAcuanAwan')
                ->importer(HargaAcuanAwanImporter::class)
                ->label('Import Harga Acuan Awan')
                ->chunkSize(250),

            ImportAction::make('importHargaAcuanOrigami')
                ->importer(HargaAcuanOrigamiImporter::class)
                ->label('Import Harga Acuan Origami')
                ->chunkSize(250),
        ];
    }

    

}