<?php

namespace App\Filament\Resources\TugasPackingResource\Pages;

use App\Filament\Resources\TugasPackingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTugasPackings extends ListRecords
{
    protected static string $resource = TugasPackingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
