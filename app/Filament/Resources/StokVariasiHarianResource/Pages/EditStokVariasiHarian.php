<?php

namespace App\Filament\Resources\StokVariasiHarianResource\Pages;

use App\Filament\Resources\StokVariasiHarianResource;
use Filament\Resources\Pages\EditRecord;

class EditStokVariasiHarian extends EditRecord
{
    protected static string $resource = StokVariasiHarianResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}