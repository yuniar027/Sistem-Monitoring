<?php

namespace App\Filament\Resources;

use BackedEnum;
use App\Filament\Resources\StokMasukResource\Pages;
use App\Models\StokMasuk;
use App\Models\ProdukMaster;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\Resource;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StokMasukResource extends Resource
{
    protected static ?string $model = StokMasuk::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-inbox';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            DatePicker::make('tanggal')->required()->default(now()),
            Select::make('sku')
                ->options(fn () => ProdukMaster::orderBy('sku')->pluck('sku', 'sku')->toArray())
                ->searchable()
                ->required(),
            TextInput::make('vendor')->required(),
            TextInput::make('kuantitas')->required()->numeric()->minValue(1),
            TextInput::make('harga_satuan')->required()->numeric()->minValue(0),
            TextInput::make('biaya_kirim')->numeric()->default(0),
            TextInput::make('total_nominal')->numeric()->required()->minValue(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal')->date(),
                TextColumn::make('sku')->sortable(),
                TextColumn::make('vendor'),
                TextColumn::make('kuantitas'),
                TextColumn::make('harga_satuan'),
                TextColumn::make('total_nominal'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStokMasuks::route('/'),
            'create' => Pages\CreateStokMasuk::route('/create'),
            'edit' => Pages\EditStokMasuk::route('/{record}/edit'),
        ];
    }
}
