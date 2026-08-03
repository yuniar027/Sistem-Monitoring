<?php

namespace App\Filament\Resources;

use BackedEnum;
use App\Filament\Resources\ResepPaketItemResource\Pages;
use App\Models\ResepPaketItem;
use App\Models\ProdukMaster;
use App\Models\BahanBaku;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Grouping\Group;

class ResepPaketItemResource extends Resource
{
    protected static ?string $model = ResepPaketItem::class;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-list-bullet';
    protected static string|\UnitEnum|null $navigationGroup = 'Produk & Bahan Baku';
    protected static ?string $navigationLabel = 'Resep Paket';
    protected static ?string $modelLabel = 'Resep Paket';
    protected static ?string $pluralModelLabel = 'Resep Paket';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('sku')
                ->options(fn () => ProdukMaster::where('tipe_produk', 'rakitan')->orderBy('sku')->pluck('sku', 'sku')->toArray())
                ->searchable()
                ->required(),
            Repeater::make('items')
                ->schema([
                    Select::make('bahan_baku_id')
                        ->options(fn () => BahanBaku::orderBy('kode_bahan')->pluck('nama_bahan', 'id')->toArray())
                        ->searchable()
                        ->required()
                        ->label('Bahan Baku'),
                    TextInput::make('kuantitas_per_paket')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->label('Kuantitas per Paket'),
                ])
                ->addActionLabel('Tambah Bahan Baku')
                ->defaultItems(1)
                ->minItems(1)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->groups([
            Group::make('sku')
                ->label('SKU')
                ->collapsible(),
            ])

            ->columns([
                TextColumn::make('produk.nama_produk')->label('Nama Produk')->sortable(),
                TextColumn::make('bahanBaku.kode_bahan')->label('Kode Bahan')->sortable(),
                TextColumn::make('bahanBaku.nama_bahan')->label('Nama Bahan')->sortable(),
                TextColumn::make('kuantitas_per_paket')->label('Kuantitas per Paket'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResepPaketItems::route('/'),
            'create' => Pages\CreateResepPaketItem::route('/create'),
            'edit' => Pages\EditResepPaketItem::route('/{record}/edit'),
        ];
    }
}