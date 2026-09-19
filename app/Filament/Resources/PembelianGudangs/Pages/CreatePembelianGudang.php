<?php

namespace App\Filament\Resources\PembelianGudangs\Pages;

use App\Filament\Resources\PembelianGudangs\PembelianGudangResource;
use App\Imports\InvoiceGudangImport;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Maatwebsite\Excel\Facades\Excel;

class CreatePembelianGudang extends CreateRecord
{
    protected static string $resource = PembelianGudangResource::class;

    public array $previewItems = [];

    public function previewInvoice(): void
    {
        $data = $this->form->getState();

        if (empty($data['file_invoice'])) {
            Notification::make()
                ->title('File invoice belum dipilih.')
                ->danger()
                ->send();

            return;
        }

        try {
            $import = new InvoiceGudangImport();

            Excel::import(
                $import,
                $data['file_invoice'],
                'local'
            );

            $this->previewItems = $import->getItems();

            if (empty($this->previewItems)) {
                Notification::make()
                    ->title('Tidak ada detail barang ditemukan.')
                    ->body('Pastikan format Excel memiliki kolom Item, Qty, Unit Price, dan Amount.')
                    ->warning()
                    ->send();

                return;
            }

            Notification::make()
                ->title('Invoice berhasil dibaca.')
                ->body(count($this->previewItems) . ' barang ditemukan.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal membaca invoice.')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('preview')
                ->label('Import & Preview')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->action('previewInvoice'),

            Action::make('create')
                ->label('Simpan Invoice')
                ->submit('create')
                ->color('primary'),

            Action::make('cancel')
                ->label('Batal')
                ->color('gray')
                ->url(
                    fn () => $this->getResource()::getUrl('index')
                ),
        ];
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(\App\Services\PembelianGudangService::class)
            ->simpanPembelian($data);
    }
}