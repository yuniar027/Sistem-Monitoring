<?php

namespace App\Filament\Resources\BiayaOperasionals\Pages;

use App\Filament\Resources\BiayaOperasionals\BiayaOperasionalResource;
use App\Models\BiayaOperasional;
use App\Services\BiayaOperasionalService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditBiayaOperasional extends EditRecord
{
    protected static string $resource = BiayaOperasionalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->modalDescription('Jurnal dan Saldo Kas yang terkait biaya ini ikut dikoreksi.')
                ->using(fn (BiayaOperasional $record) => app(BiayaOperasionalService::class)->hapusBiaya($record)),
        ];
    }

    // Lewat service supaya jurnal ikut diperbarui.
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(BiayaOperasionalService::class)->perbaruiBiaya($record, $data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
