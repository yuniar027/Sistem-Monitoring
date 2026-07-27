<?php

namespace App\Filament\Resources\TugasPackingResource\Pages;

use App\Filament\Resources\TugasPackingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTugasPacking extends EditRecord
{
    protected static string $resource = TugasPackingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
