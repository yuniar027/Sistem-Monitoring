<?php

namespace App\Filament\Resources\TransaksiPenjualanResource\Pages;

use App\Filament\Resources\TransaksiPenjualanResource;
use App\Services\TransaksiPenjualanService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\App;
use Throwable;

class CreateTransaksiPenjualan extends CreateRecord
{
    protected static string $resource = TransaksiPenjualanResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            $service = App::make(TransaksiPenjualanService::class);

            return $service->catatPenjualan($data);
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Gagal mencatat transaksi penjualan')
                ->body($exception->getMessage())
                ->send();

            $this->halt(shouldRollbackDatabaseTransaction: true);
        }
    }
}
