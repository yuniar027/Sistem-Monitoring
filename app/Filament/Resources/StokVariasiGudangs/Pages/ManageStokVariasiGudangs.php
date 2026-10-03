<?php

namespace App\Filament\Resources\StokVariasiGudangs\Pages;

use App\Filament\Resources\StokVariasiGudangs\StokVariasiGudangResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStokVariasiGudangs extends ManageRecords
{
    protected static string $resource = StokVariasiGudangResource::class;

    protected function getHeaderActions(): array
    {
        if (static::getResource()::isPabrik()) {
            return [];
        }

        return [
            CreateAction::make(),
        ];
    }
}
