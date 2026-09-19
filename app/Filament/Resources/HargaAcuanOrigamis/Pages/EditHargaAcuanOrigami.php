<?php

namespace App\Filament\Resources\HargaAcuanOrigamis\Pages;

use App\Filament\Resources\HargaAcuanOrigamis\HargaAcuanOrigamiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHargaAcuanOrigami extends EditRecord
{
    protected static string $resource = HargaAcuanOrigamiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
