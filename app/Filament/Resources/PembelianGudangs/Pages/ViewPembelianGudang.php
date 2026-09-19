<?php

namespace App\Filament\Resources\PembelianGudangs\Pages;

use App\Filament\Resources\PembelianGudangs\PembelianGudangResource;
use App\Filament\Resources\PembelianGudangs\Schemas\PembelianGudangInfolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;

class ViewPembelianGudang extends ViewRecord
{
    protected static string $resource = PembelianGudangResource::class;

    public function infolist(Schema $schema): Schema
    {
        return PembelianGudangInfolist::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('tandaiSudahDicek')
                ->label('Tandai Perubahan Sudah Dicek')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(function (): bool {
                    return $this->record
                        ->detail()
                        ->whereIn('kategori_perbandingan', ['naik', 'turun'])
                        ->where('sudah_dicek', false)
                        ->exists();
                })
                ->action(function (): void {
                    $jumlah = $this->record
                        ->detail()
                        ->whereIn('kategori_perbandingan', ['naik', 'turun'])
                        ->where('sudah_dicek', false)
                        ->update([
                            'sudah_dicek' => true,
                            'dicek_pada' => now(),
                        ]);

                    Notification::make()
                        ->title('Perubahan harga sudah ditandai sebagai dicek.')
                        ->body("{$jumlah} perubahan harga berhasil ditandai.")
                        ->success()
                        ->send();
                }),
        ];
    }
}