<?php

namespace App\Filament\Resources\BiayaOperasionals\Pages;

use App\Filament\Resources\BiayaOperasionals\BiayaOperasionalResource;
use App\Services\BiayaOperasionalService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBiayaOperasional extends CreateRecord
{
    protected static string $resource = BiayaOperasionalResource::class;

    protected static bool $canCreateAnother = false;

    // Lewat service supaya jurnal (debit biaya, kredit kas) ikut tercatat.
    protected function handleRecordCreation(array $data): Model
    {
        return app(BiayaOperasionalService::class)->catatBiaya($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
