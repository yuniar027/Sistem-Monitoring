<?php

namespace App\Filament\Resources\TugasPackingResource\Pages;

use App\Filament\Resources\TugasPackingResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;

class ListTugasPackings extends ListRecords
{
    protected static string $resource = TugasPackingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateUlangJadwal')
                ->label('Generate Ulang Jadwal')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Generate Ulang Jadwal Setting?')
                ->modalDescription('Ini akan menghapus tugas auto-generate yang belum selesai dan membuat ulang berdasarkan stok terbaru.')
                ->action(function () {
                    Artisan::call('jadwal:generate');

                    Notification::make()
                        ->title('Jadwal berhasil di-generate ulang')
                        ->body(trim(Artisan::output()))
                        ->success()
                        ->send();
                }),

            CreateAction::make(),
        ];
    }
}