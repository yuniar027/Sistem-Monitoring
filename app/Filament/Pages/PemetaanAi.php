<?php

namespace App\Filament\Pages;

use App\Services\PemetaanBahanBakuService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\App;

class PemetaanAi extends Page implements \Filament\Forms\Contracts\HasForms
{
    use \Filament\Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationLabel = 'AI Mapping';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-sparkles';
    protected static ?string $title = 'AI Mapping Bahan Baku';

    protected string $view = 'filament.pages.pemetaan-ai';

    public ?string $daftarNama = '';

    public array $hasil = [];

    public bool $sudahDijalankan = false;

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Textarea::make('daftarNama')
                ->label('Tempel nama item dari invoice pabrik (1 baris = 1 item)')
                ->rows(10)
                ->placeholder("ST CLN POP ABU\nJUMPER S4 CREM\nBEDONG ABU"),
        ]);
    }

    protected function getFormStatePath(): string
    {
        return 'data';
    }

    public array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function petakan(): void
    {
        $namaList = array_filter(array_map('trim', explode("\n", $this->data['daftarNama'] ?? '')));

        if (empty($namaList)) {
            return;
        }

        $service = App::make(PemetaanBahanBakuService::class);
        $this->hasil = $service->petakanBanyak($namaList);
        $this->sudahDijalankan = true;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
