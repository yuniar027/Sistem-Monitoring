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
    protected static string|\UnitEnum|null $navigationGroup = 'Stok & Gudang';
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
            TextInput::make('jumlah_target')
                ->label('Jumlah Target Dirakit (dari bahan baku)')
                ->helperText('Jumlah yang dicoba dirakit — bahan baku dipotong sesuai angka ini, walau hasil akhirnya kurang dari ini karena reject.')
                ->numeric()
                ->minValue(1)
                ->required(),
            TextInput::make('jumlah_jadi')
                ->label('Jumlah yang Benar-benar Jadi')
                ->helperText('Setelah dirakit, berapa yang lolos QC dan siap jual? Sisanya otomatis dihitung sebagai reject.')
                ->numeric()
                ->minValue(0)
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
            $stokPaket = $service->rakitPaket($data);

            if ($stokPaket->status_reject === 'melebihi_batas') {
                Notification::make()
                    ->title('Paket berhasil dirakit — TAPI reject melebihi batas aman!')
                    ->body("Reject {$stokPaket->jumlah_reject} dari {$stokPaket->jumlah_target} target ({$stokPaket->persentase_reject}%). Segera laporkan ke pabrik.")
                    ->warning()
                    ->persistent()
                    ->send();
            } else {
                Notification::make()->title('Paket berhasil dirakit')->success()->send();
            }

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