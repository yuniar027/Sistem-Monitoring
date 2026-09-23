<?php

namespace App\Filament\Resources\ProductionProcessTargets\Pages;

use App\Filament\Resources\ProductionProcessTargets\ProductionProcessTargetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProductionProcessTargets extends ManageRecords
{
    protected static string $resource = ProductionProcessTargetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}