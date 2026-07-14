<?php

namespace App\Filament\Resources\StokMasukResource\Pages;

use App\Filament\Resources\StokMasukResource;
use App\Services\StokMasukService;
use Filament\Resources\Pages\CreateRecord;

class CreateStokMasuk extends CreateRecord
{
    protected static string $resource = StokMasukResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $service = app(StokMasukService::class);

        return $service->catatStokMasuk($data);
    }
}
