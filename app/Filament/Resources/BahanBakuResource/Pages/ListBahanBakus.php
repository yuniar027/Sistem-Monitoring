<?php

namespace App\Filament\Resources\BahanBakuResource\Pages;

use App\Filament\Imports\BahanBakuImporter;
use App\Filament\Resources\BahanBakuResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListBahanBakus extends ListRecords
{
    protected static string $resource = BahanBakuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(BahanBakuImporter::class)
                ->label('Import Bahan Baku')
                ->chunkSize(50),
            CreateAction::make(),
        ];
    }
}
