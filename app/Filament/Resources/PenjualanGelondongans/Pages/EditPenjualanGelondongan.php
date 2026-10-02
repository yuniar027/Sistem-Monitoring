<?php

namespace App\Filament\Resources\PenjualanGelondongans\Pages;

use App\Filament\Resources\PenjualanGelondongans\PenjualanGelondonganResource;
use App\Models\PenjualanGelondongan;
use App\Services\PenjualanGelondonganService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPenjualanGelondongan extends EditRecord
{
    protected static string $resource = PenjualanGelondonganResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->modalDescription('Jurnal, Saldo Kas, dan laporan laba rugi yang terkait ikut dikoreksi.')
                ->using(fn (PenjualanGelondongan $record) => app(PenjualanGelondonganService::class)->hapus($record)),
        ];
    }

    // Lewat service supaya jurnal ikut diperbarui.
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(PenjualanGelondonganService::class)->perbarui($record, $data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
