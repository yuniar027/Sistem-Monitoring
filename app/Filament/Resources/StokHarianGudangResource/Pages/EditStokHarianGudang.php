<?php

namespace App\Filament\Resources\StokHarianGudangResource\Pages;

use App\Filament\Resources\StokHarianGudangResource;
use Filament\Resources\Pages\EditRecord;

class EditStokHarianGudang extends EditRecord
{
    protected static string $resource = StokHarianGudangResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}