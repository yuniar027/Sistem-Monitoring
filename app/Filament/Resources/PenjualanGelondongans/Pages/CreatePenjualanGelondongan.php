<?php

namespace App\Filament\Resources\PenjualanGelondongans\Pages;

use App\Filament\Resources\PenjualanGelondongans\PenjualanGelondonganResource;
use App\Services\PenjualanGelondonganService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePenjualanGelondongan extends CreateRecord
{
    protected static string $resource = PenjualanGelondonganResource::class;

    protected static bool $canCreateAnother = false;

    // Lewat service supaya jurnal penjualan (dan HPP jika diisi) ikut tercatat.
    protected function handleRecordCreation(array $data): Model
    {
        return app(PenjualanGelondonganService::class)->catat($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
