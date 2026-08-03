<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Forms\Components\FileUpload;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

class ImportTransaksiHarian extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-arrow-up';
    protected static string|\UnitEnum|null $navigationGroup = 'Otomasi & Import';
    protected static ?string $navigationLabel = 'Import Transaksi Harian';
    protected static ?string $title = 'Import Transaksi Harian';

    protected string $view = 'filament.pages.import-transaksi-harian';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                FileUpload::make('file')
                    ->label('File Excel Transaksi (.xlsx)')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->disk('local')
                    ->directory('imports')
                    ->visibility('private')
                    ->required()
                    ->maxSize(10240)
                    ->helperText('Maksimal 10MB. Hanya format .xlsx.'),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('submit')
                ->label('Upload & Kirim ke Sistem Otomasi')
                ->submit('submit')
                ->color('primary'),
        ];
    }

    public function submit(): void
    {
        $state = $this->form->getState();
        $path = $state['file'];
        $filename = basename($path);

        $signedUrl = URL::temporarySignedRoute(
            'imports.download',
            now()->addMinutes(5),
            ['filename' => $filename]
        );

        $response = Http::withHeaders([
            'X-Webhook-Token' => config('services.n8n.webhook_token'),
        ])->post(config('services.n8n.import_webhook_url'), [
            'file_url'        => $signedUrl,
            'uploaded_by_id'  => auth()->id(),
            'uploaded_by_name'=> auth()->user()->name,
            'uploaded_at'     => now()->toIso8601String(),
        ]);

        if ($response->successful()) {
            Notification::make()
                ->title('Berhasil')
                ->body('File dikirim ke sistem otomasi, tunggu beberapa saat untuk diproses.')
                ->success()
                ->send();

            $this->form->fill();
        } else {
            Notification::make()
                ->title('Gagal mengirim file')
                ->body('n8n merespons status: ' . $response->status())
                ->danger()
                ->send();
        }
    }
}