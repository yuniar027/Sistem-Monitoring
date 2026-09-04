<?php

namespace App\Filament\Resources\StokBarangGudangResource\Pages;

use App\Filament\Resources\StokBarangGudangResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStokBarangGudang extends EditRecord
{
    protected static string $resource = StokBarangGudangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}