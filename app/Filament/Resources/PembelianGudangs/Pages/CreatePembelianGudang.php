<?php

namespace App\Filament\Resources\PembelianGudangs\Pages;

use App\Filament\Resources\PembelianGudangs\PembelianGudangResource;
use App\Imports\InvoiceGudangImport;
use App\Models\StokBarangGudang;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

class CreatePembelianGudang extends CreateRecord
{
    protected static string $resource = PembelianGudangResource::class;

    public array $previewItems = [];

    public ?string $previewNomorInvoice = null;

    public ?string $previewTanggal = null;

    public ?string $previewSupplier = null;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Import Invoice')
                    ->schema([
                        FileUpload::make('file_invoice')
                            ->label('File Excel Invoice')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                                'text/csv',
                            ])
                            ->disk('local')
                            ->directory('invoice-gudang')
                            ->required()
                            ->columnSpanFull(),

                        Textarea::make('catatan')
                            ->label('Catatan')
                            ->rows(3)
                            ->nullable()
                            ->columnSpanFull(),
                    ]),

                Section::make('Preview Invoice')
                    ->schema([
                        Placeholder::make('preview_invoice')
                            ->hiddenLabel()
                            ->content(function ($livewire) {
                                $items = $livewire->previewItems ?? [];

                                if (empty($items)) {
                                    return 'Belum ada invoice yang di-import. Klik "Import & Preview" setelah memilih file.';
                                }

                                return view(
                                    'filament.pembelian-gudang.preview-invoice',
                                    [
                                        'items' => $items,
                                        'nomorInvoice' => $livewire->previewNomorInvoice,
                                        'tanggal' => $livewire->previewTanggal,
                                        'supplier' => $livewire->previewSupplier,
                                    ]
                                );
                            }),
                    ])
                    ->columnSpanFull(),
            ]);
    }

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
            $this->previewNomorInvoice = $import->getNomorInvoice();
            $this->previewTanggal = $import->getTanggal();
            $this->previewSupplier = $import->getSupplier();

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
        $nomorInvoiceExcel = $this->previewNomorInvoice;
        $tanggalExcel = $this->previewTanggal;
        $supplierExcel = $this->previewSupplier;

        if (empty($items) && ! empty($data['file_invoice'])) {
            $import = new InvoiceGudangImport();

            Excel::import(
                $import,
                $data['file_invoice'],
                'local'
            );

            $items = $import->getItems();
            $nomorInvoiceExcel = $import->getNomorInvoice();
            $tanggalExcel = $import->getTanggal();
            $supplierExcel = $import->getSupplier();
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

        $nomorInvoice = $nomorInvoiceExcel ?? ('INV-' . now()->format('YmdHis'));
        $tanggal = $tanggalExcel ?? now()->toDateString();

        if (\App\Models\PembelianGudang::where('nomor_invoice', $nomorInvoice)->exists()) {
            throw new RuntimeException(
                "Invoice dengan nomor \"{$nomorInvoice}\" sudah pernah diimport sebelumnya."
            );
        }

        $data['nomor_invoice'] = $nomorInvoice;
        $data['tanggal'] = $tanggal;
        $data['supplier'] = $supplierExcel ?? ($data['supplier'] ?? null);
        $data['detail'] = $detail;

        return app(\App\Services\PembelianGudangService::class)
            ->simpanPembelian($data);
    }
}