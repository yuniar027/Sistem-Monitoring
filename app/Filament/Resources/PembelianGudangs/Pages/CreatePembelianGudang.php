<?php

namespace App\Filament\Resources\PembelianGudangs\Pages;

use App\Filament\Resources\PembelianGudangs\PembelianGudangResource;
use App\Imports\InvoiceGudangImport;
use App\Models\StokBarangGudang;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

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
                    ->body(
                        'Pastikan format Excel memiliki kolom KODE, BARANG, JUMLAH, HARGA, dan SUBTOTAL.'
                    )
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

    protected function handleRecordCreation(
        array $data
    ): \Illuminate\Database\Eloquent\Model {
        $items = $this->previewItems;

        /*
         * Kalau preview belum dijalankan atau state preview hilang,
         * baca ulang file invoice sebelum menyimpan.
         */
        if (empty($items) && ! empty($data['file_invoice'])) {
            $import = new InvoiceGudangImport();

            Excel::import(
                $import,
                $data['file_invoice'],
                'local'
            );

            $items = $import->getItems();
        }

        if (empty($items)) {
            throw new RuntimeException(
                'Tidak ada barang yang berhasil dibaca dari invoice.'
            );
        }

        $detail = [];

        foreach ($items as $item) {
            $kode = trim((string) ($item['kode'] ?? ''));
            $nama = trim((string) ($item['item'] ?? ''));

            $barang = null;

            if ($kode !== '') {
                $barang = StokBarangGudang::query()
                    ->where('kode_barang', $kode)
                    ->where(
                        'kategori',
                        StokBarangGudang::KATEGORI_ORIGAMI
                    )
                    ->first();
            }

            $detail[] = [
                'barang_gudang_id' => $barang?->id,
                'kode_barang_invoice' => $kode !== '' ? $kode : null,
                'nama_barang_invoice' => $nama !== '' ? $nama : null,
                'kuantitas' => (float) ($item['qty'] ?? 0),
                'harga_invoice' => (float) ($item['unit_price'] ?? 0),
                'catatan' => $barang
                    ? null
                    : 'Barang belum terpetakan ke Master Barang Gudang.',
            ];
        }

        if (empty($detail)) {
            throw new RuntimeException(
                'Tidak ada detail invoice yang valid untuk disimpan.'
            );
        }

        $data['detail'] = $detail;

        return app(\App\Services\PembelianGudangService::class)
            ->simpanPembelian($data);
    }
}
