<?php

namespace App\Filament\Resources\PenjualanGelondongans\Pages;

use App\Filament\Resources\PenjualanGelondongans\PenjualanGelondonganResource;
use App\Models\PenjualanGelondongan;
use App\Services\PenjualanGelondonganService;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPenjualanGelondongan extends ViewRecord
{
    protected static string $resource = PenjualanGelondonganResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make()
                ->modalDescription('Jurnal, Saldo Kas, dan laporan laba rugi yang terkait ikut dikoreksi.')
                ->using(fn (PenjualanGelondongan $record) => app(PenjualanGelondonganService::class)->hapus($record))
                ->successRedirectUrl(fn () => $this->getResource()::getUrl('index')),
        ];
    }
}
