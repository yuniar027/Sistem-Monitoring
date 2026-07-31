<?php

namespace App\Filament\Resources\PembayaranHutangs\Pages;

use App\Filament\Resources\PembayaranHutangs\PembayaranHutangResource;
use App\Services\PembayaranHutangService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;

class ManagePembayaranHutangs extends ManageRecords
{
    protected static string $resource = PembayaranHutangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(function (array $data): Model {
                    $service = app(PembayaranHutangService::class);
                    return $service->catatPembayaran($data);
                }),
        ];
    }
}