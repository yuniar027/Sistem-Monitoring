<?php

namespace App\Filament\Pages;

use App\Models\BiayaOperasional;
use App\Services\BiayaOperasionalService;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class BiayaOperasionalPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-banknotes';
    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';
    protected static ?string $navigationLabel = 'Biaya Operasional';
    protected static ?string $title = 'Biaya Operasional';

    protected string $view = 'filament.pages.biaya-operasional-page';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('kategori')
                ->label('Kategori')
                ->options(config('kategori_biaya'))
                ->required(),
            DatePicker::make('tanggal')
                ->label('Tanggal')
                ->default(now())
                ->required(),
            TextInput::make('keterangan')
                ->label('Keterangan (opsional)'),
            TextInput::make('nominal')
                ->label('Nominal')
                ->numeric()
                ->minValue(0)
                ->required(),
        ])->statePath('data');
    }

    public function submit(BiayaOperasionalService $service): void
    {
        $data = $this->form->getState();

        $service->catatBiaya($data);

        Notification::make()->title('Biaya operasional berhasil dicatat')->success()->send();

        $this->form->fill();
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(BiayaOperasional::query())
            ->columns([
                TextColumn::make('tanggal')->date(),
                TextColumn::make('kategori')->formatStateUsing(fn ($state) => config('kategori_biaya.' . $state, $state)),
                TextColumn::make('keterangan'),
                TextColumn::make('nominal')->money('IDR'),
            ])
            ->defaultSort('tanggal', 'desc');
    }
}