<?php

namespace App\Filament\Resources\BahanBakuMasukResource\Pages;

use App\Filament\Resources\BahanBakuMasukResource;
use App\Services\BahanBakuMasukService;
use Filament\Resources\Pages\CreateRecord;

class CreateBahanBakuMasuk extends CreateRecord
{
    protected static string $resource = BahanBakuMasukResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $service = app(BahanBakuMasukService::class);

        return $service->catatBahanBakuMasuk($data);
    }
}
