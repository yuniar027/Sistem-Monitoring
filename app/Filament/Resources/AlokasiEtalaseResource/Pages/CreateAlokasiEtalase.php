<?php

namespace App\Filament\Resources\AlokasiEtalaseResource\Pages;

use App\Filament\Resources\AlokasiEtalaseResource;
use App\Services\AlokasiEtalaseService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;

class CreateAlokasiEtalase extends CreateRecord
{
    protected static string $resource = AlokasiEtalaseResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            $service = App::make(AlokasiEtalaseService::class);

            return $service->buatAlokasi($data);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())
                ->flatten()
                ->first() ?? 'Terjadi kesalahan saat membuat alokasi etalase.';

            Notification::make()
                ->danger()
                ->title('Gagal membuat alokasi etalase')
                ->body($message)
                ->send();

            $this->halt(shouldRollbackDatabaseTransaction: true);
        }
    }
}
