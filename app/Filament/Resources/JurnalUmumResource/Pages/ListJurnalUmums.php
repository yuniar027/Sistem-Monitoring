<?php

namespace App\Filament\Resources\JurnalUmumResource\Pages;

use App\Filament\Resources\JurnalUmumResource;
use App\Filament\Widgets\RingkasanJurnalWidget;
use Filament\Resources\Pages\ListRecords;

class ListJurnalUmums extends ListRecords
{
    protected static string $resource = JurnalUmumResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            RingkasanJurnalWidget::class,
        ];
    }
}
