<?php

namespace App\Filament\Resources\PenjualanGelondongans\Pages;

use App\Filament\Resources\PenjualanGelondongans\PenjualanGelondonganResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPenjualanGelondongans extends ListRecords
{
    protected static string $resource = PenjualanGelondonganResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Penjualan'),
        ];
    }
}
