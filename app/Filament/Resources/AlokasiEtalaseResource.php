<?php

namespace App\Filament\Resources;

use BackedEnum;
use App\Filament\Resources\AlokasiEtalaseResource\Pages;
use App\Models\AlokasiEtalase;
use App\Models\ProdukMaster;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Schemas\Schema;

class AlokasiEtalaseResource extends Resource
{
    protected static ?string $model = AlokasiEtalase::class;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-inbox-stack';
    protected static ?string $navigationLabel = 'Alokasi Etalase';
    protected static ?string $modelLabel = 'Alokasi Etalase';
    protected static ?string $pluralModelLabel = 'Alokasi Etalase';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('sku')
                ->options(fn () => ProdukMaster::orderBy('sku')->pluck('sku', 'sku')->toArray())
                ->searchable()
                ->required(),
            Select::make('channel')
                ->options([
                    'shopee' => 'Shopee',
                    'tiktok' => 'TikTok',
                ])
                ->required(),
            TextInput::make('nama_toko')->required(),
            TextInput::make('kuantitas_dialokasikan')->required()->numeric()->minValue(1),
            DatePicker::make('tanggal_alokasi')->required()->default(now()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')->sortable(),
                TextColumn::make('produk.nama_produk')->label('Nama Produk'),
                TextColumn::make('channel'),
                TextColumn::make('nama_toko'),
                TextColumn::make('kuantitas_dialokasikan'),
                TextColumn::make('kuantitas_terjual'),
                TextColumn::make('kuantitas_sisa'),
                TextColumn::make('tanggal_alokasi')->date(),
                TextColumn::make('status'),
            ])
            ->defaultSort('tanggal_alokasi', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAlokasiEtalases::route('/'),
            'create' => Pages\CreateAlokasiEtalase::route('/create'),
            'edit' => Pages\EditAlokasiEtalase::route('/{record}/edit'),
        ];
    }
}