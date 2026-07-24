<?php

namespace App\Filament\Resources;

use BackedEnum;
use App\Filament\Resources\BahanBakuResource\Pages;
use App\Models\BahanBaku;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;

class BahanBakuResource extends Resource
{
    protected static ?string $model = BahanBaku::class;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationLabel = 'Bahan Baku';
    protected static ?string $modelLabel = 'Bahan Baku';
    protected static ?string $pluralModelLabel = 'Bahan Baku';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('kode_bahan')->required()->unique(),
            TextInput::make('nama_bahan')->required(),
            TextInput::make('satuan_beli')->required(),
            TextInput::make('isi_per_satuan_beli')->required()->numeric()->minValue(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode_bahan')->sortable()->searchable(),
                TextColumn::make('nama_bahan')->sortable()->searchable(),
                TextColumn::make('satuan_beli'),
                TextColumn::make('isi_per_satuan_beli'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBahanBakus::route('/'),
            'create' => Pages\CreateBahanBaku::route('/create'),
            'edit' => Pages\EditBahanBaku::route('/{record}/edit'),
        ];
    }
}