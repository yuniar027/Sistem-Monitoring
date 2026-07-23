<?php

namespace App\Filament\Resources\ResepPaketItemResource\Pages;

use App\Filament\Imports\ResepPaketItemImporter;
use App\Filament\Resources\ResepPaketItemResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListResepPaketItems extends ListRecords
{
    protected static string $resource = ResepPaketItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(ResepPaketItemImporter::class)
                ->label('Import Resep Paket')
                ->chunkSize(50),
            CreateAction::make(),
        ];
    }
}
