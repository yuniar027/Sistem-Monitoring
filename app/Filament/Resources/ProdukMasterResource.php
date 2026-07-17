<?php

namespace App\Filament\Resources;

use BackedEnum;
use App\Filament\Resources\ProdukMasterResource\Pages;
use App\Models\ProdukMaster;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;

class ProdukMasterResource extends Resource
{
        protected static ?string $model = ProdukMaster::class;
        protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';
        protected static ?string $navigationLabel = 'Produk Master';
        protected static ?string $modelLabel = 'Produk Master';
        protected static ?string $pluralModelLabel = 'Produk Master';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('sku')->required()->unique(ignoreRecord: true),
            Select::make('tipe_produk')
                ->options([
                    'simple' => 'Simple',
                    'rakitan' => 'Rakitan',
                ])
                ->required(),
            TextInput::make('nama_produk')->required(),
            TextInput::make('satuan_jual')->required(),
            TextInput::make('satuan_beli')->required(),
            TextInput::make('isi_per_satuan_beli')->required()->numeric()->default(1),
            TextInput::make('kategori')->nullable(),
            TextInput::make('harga_modal_default')->numeric()->nullable(),
            TextInput::make('target_stok_minimum')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')->sortable()->searchable(),
                TextColumn::make('nama_produk')->limit(50)->searchable(),
                TextColumn::make('satuan_jual'),
                TextColumn::make('satuan_beli'),
                TextColumn::make('isi_per_satuan_beli'),
                TextColumn::make('target_stok_minimum'),
            ])
            ->filters([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProdukMasters::route('/'),
            'create' => Pages\CreateProdukMaster::route('/create'),
            'edit' => Pages\EditProdukMaster::route('/{record}/edit'),
        ];
    }
}