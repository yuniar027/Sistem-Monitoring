<?php

namespace App\Filament\Resources\HargaAcuanAwans\Pages;

use App\Filament\Resources\HargaAcuanAwans\HargaAcuanAwanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHargaAcuanAwan extends EditRecord
{
    protected static string $resource = HargaAcuanAwanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}