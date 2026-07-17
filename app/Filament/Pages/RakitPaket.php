<?php

namespace App\Filament\Pages;

use App\Models\ProdukMaster;
use App\Services\RakitPaketService;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class RakitPaket extends Page implements HasForms
{
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationLabel = 'Rakit Paket';
    protected static ?string $title = 'Rakit Paket';

    protected string $view = 'filament.pages.rakit-paket';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('sku')
                ->label('SKU Produk Rakitan')
                ->options(fn () => ProdukMaster::where('tipe_produk', 'rakitan')->orderBy('sku')->pluck('sku', 'sku')->toArray())
                ->searchable()
                ->required(),
            TextInput::make('jumlah_paket')
                ->label('Jumlah Paket yang Dirakit')
                ->numeric()
                ->minValue(1)
                ->required(),
            DatePicker::make('tanggal_dibuat')
                ->label('Tanggal')
                ->default(now())
                ->required(),
        ])->statePath('data');
    }

    public function submit(RakitPaketService $service): void
    {
        $data = $this->form->getState();

        try {
            $service->rakitPaket($data);

            Notification::make()->title('Paket berhasil dirakit')->success()->send();

            $this->form->fill();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Gagal merakit paket')
                ->body(collect($exception->errors())->flatten()->implode(' '))
                ->danger()
                ->send();
        }
    }
}