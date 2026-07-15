<?php

namespace App\Filament\Resources\StokPaketResource\Pages;

use App\Filament\Resources\StokPaketResource;
use App\Services\StokPaketService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;

class CreateStokPaket extends CreateRecord
{
    protected static string $resource = StokPaketResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            $service = App::make(StokPaketService::class);

            return $service->buatPaket($data);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())
                ->flatten()
                ->first() ?? 'Terjadi kesalahan saat membuat paket.';

            Notification::make()
                ->danger()
                ->title('Gagal membuat paket')
                ->body($message)
                ->send();

            $this->halt(shouldRollbackDatabaseTransaction: true);
        }
    }
}
