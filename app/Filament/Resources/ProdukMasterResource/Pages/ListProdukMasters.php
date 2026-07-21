<?php

namespace App\Filament\Resources\ProdukMasterResource\Pages;

use App\Filament\Imports\ProdukMasterImporter;
use App\Filament\Resources\ProdukMasterResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListProdukMasters extends ListRecords
{
    protected static string $resource = ProdukMasterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(ProdukMasterImporter::class)
                ->label('Import Produk Master')
                ->chunkSize(50),
            CreateAction::make(),
        ];
    }
}
