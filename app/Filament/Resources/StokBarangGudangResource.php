<?php

namespace App\Filament\Resources;

use BackedEnum;
use App\Filament\Resources\StokBarangGudangResource\Pages;
use App\Filament\Resources\StokBarangGudangResource\RelationManagers\VariasiRelationManager;
use App\Models\StokBarangGudang;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StokBarangGudangResource extends Resource
{
    protected static ?string $model = StokBarangGudang::class;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-archive-box';
    protected static string|\UnitEnum|null $navigationGroup = 'Monitoring Stok Ringkas';
    protected static ?string $navigationLabel = 'Master Barang Gudang';
    protected static ?string $modelLabel = 'Barang Gudang';
    protected static ?string $pluralModelLabel = 'Master Barang Gudang';

    public static function kategoriOptions(): array
    {
        return [
            StokBarangGudang::KATEGORI_AWAN => 'Awan',
            StokBarangGudang::KATEGORI_ORIGAMI => 'Origami',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Radio::make('kategori')
                ->options(static::kategoriOptions())
                ->required()
                ->default(StokBarangGudang::KATEGORI_AWAN)
                ->inline(),
            TextInput::make('kode_barang')
                ->helperText('Kosongkan untuk generate otomatis dari nama barang')
                ->maxLength(50),
            TextInput::make('nama_barang')
                ->required()
                ->maxLength(255),
            TextInput::make('stok_aman')
                ->required()
                ->numeric()
                ->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kategori')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => static::kategoriOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => $state === StokBarangGudang::KATEGORI_ORIGAMI ? 'warning' : 'info'),
                TextColumn::make('kode_barang')->searchable()->sortable(),
                TextColumn::make('nama_barang')->searchable()->sortable(),
                TextColumn::make('stok_aman')->label('Stok Aman')->sortable(),
                TextColumn::make('variasi_count')
                    ->label('Jumlah Variasi')
                    ->counts('variasi'),
            ])
            ->filters([
                SelectFilter::make('kategori')->options(static::kategoriOptions()),
            ])
            ->defaultSort('nama_barang');
    }

    public static function getRelations(): array
    {
        return [
            VariasiRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStokBarangGudangs::route('/'),
            'create' => Pages\CreateStokBarangGudang::route('/create'),
            'edit' => Pages\EditStokBarangGudang::route('/{record}/edit'),
        ];
    }
}