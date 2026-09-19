<?php

namespace App\Filament\Resources\PembelianGudangs\Pages;

use App\Filament\Resources\PembelianGudangs\PembelianGudangResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPembelianGudangs extends ListRecords
{
    protected static string $resource = PembelianGudangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
