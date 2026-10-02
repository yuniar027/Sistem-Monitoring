<?php

namespace App\Filament\Resources\BiayaOperasionals\Pages;

use App\Filament\Resources\BiayaOperasionals\BiayaOperasionalResource;
use App\Models\BiayaOperasional;
use App\Services\BiayaOperasionalService;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBiayaOperasional extends ViewRecord
{
    protected static string $resource = BiayaOperasionalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make()
                ->modalDescription('Jurnal dan Saldo Kas yang terkait biaya ini ikut dikoreksi.')
                ->using(fn (BiayaOperasional $record) => app(BiayaOperasionalService::class)->hapusBiaya($record))
                ->successRedirectUrl(fn () => $this->getResource()::getUrl('index')),
        ];
    }
}
