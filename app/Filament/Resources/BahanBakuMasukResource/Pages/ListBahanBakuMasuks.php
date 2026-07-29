<?php

namespace App\Filament\Resources\BahanBakuMasukResource\Pages;

use App\Filament\Resources\BahanBakuMasukResource;
use App\Filament\Imports\BahanBakuMasukImporter;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListBahanBakuMasuks extends ListRecords
{
    protected static string $resource = BahanBakuMasukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(BahanBakuMasukImporter::class),
            CreateAction::make(),
        ];
    }
}