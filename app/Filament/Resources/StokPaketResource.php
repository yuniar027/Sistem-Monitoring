<?php

namespace App\Filament\Resources;

use BackedEnum;
use App\Filament\Resources\StokPaketResource\Pages;
use App\Models\StokPaket;
use App\Models\ProdukMaster;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Schemas\Schema;

class StokPaketResource extends Resource
{
    protected static ?string $model = StokPaket::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cube';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('sku')
                ->options(fn () => ProdukMaster::orderBy('sku')->pluck('sku', 'sku')->toArray())
                ->searchable()
                ->required(),
            TextInput::make('kuantitas_per_paket')->required()->numeric()->minValue(1),
            TextInput::make('jumlah_paket')->required()->numeric()->minValue(1),
            DatePicker::make('tanggal_dibuat')->required()->default(now()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')->sortable(),
                TextColumn::make('produk.nama_produk')->label('Nama Produk'),
                TextColumn::make('kuantitas_per_paket'),
                TextColumn::make('jumlah_paket'),
                TextColumn::make('tanggal_dibuat')->date(),
                TextColumn::make('status'),
            ])
            ->defaultSort('tanggal_dibuat', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStokPakets::route('/'),
            'create' => Pages\CreateStokPaket::route('/create'),
            'edit' => Pages\EditStokPaket::route('/{record}/edit'),
        ];
    }
}
