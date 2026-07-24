<?php

namespace App\Filament\Resources;

use BackedEnum;
use App\Filament\Resources\TransaksiPenjualanResource\Pages;
use App\Models\ProdukMaster;
use App\Models\TransaksiPenjualan;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Schemas\Schema;

class TransaksiPenjualanResource extends Resource
{
    protected static ?string $model = TransaksiPenjualan::class;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'Transaksi Penjualan';
    protected static ?string $modelLabel = 'Transaksi Penjualan';
    protected static ?string $pluralModelLabel = 'Transaksi Penjualan';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('channel')
                ->options([
                    'shopee' => 'Shopee',
                    'tiktok' => 'TikTok',
                ])
                ->required(),

            TextInput::make('no_pesanan')
                ->label('No. Pesanan')
                ->required()
                ->unique(ignoreRecord: true),

            TextInput::make('no_resi')
                ->label('No. Resi')
                ->nullable(),

            Select::make('sku')
                ->options(fn () => ProdukMaster::orderBy('sku')->pluck('sku', 'sku')->toArray())
                ->searchable()
                ->required(),

            TextInput::make('jumlah')
                ->numeric()
                ->required()
                ->minValue(1)
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, $get, $set) {
                    $set('total', (float) $state * (float) $get('harga'));
                }),

            TextInput::make('harga')
                ->label('Harga Satuan')
                ->numeric()
                ->required()
                ->prefix('Rp')
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, $get, $set) {
                    $set('total', (float) $state * (float) $get('jumlah'));
                }),

            TextInput::make('total')
                ->numeric()
                ->required()
                ->prefix('Rp')
                ->helperText('Otomatis terisi dari Harga x Jumlah, tapi bisa diedit manual kalau ada diskon/selisih.'),

            DatePicker::make('tanggal')
                ->required()
                ->default(now()),

            TextInput::make('status_order')
                ->label('Status Order')
                ->required()
                ->default('selesai'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no_pesanan')->label('No. Pesanan')->searchable(),
                TextColumn::make('channel'),
                TextColumn::make('sku')->searchable(),
                TextColumn::make('produk.nama_produk')->label('Nama Produk')->limit(40),
                TextColumn::make('jumlah'),
                TextColumn::make('harga')->money('IDR'),
                TextColumn::make('total')->money('IDR'),
                TextColumn::make('tanggal')->date(),
                TextColumn::make('status_order')->badge(),
            ])
            ->defaultSort('tanggal', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransaksiPenjualans::route('/'),
            'create' => Pages\CreateTransaksiPenjualan::route('/create'),
            'edit' => Pages\EditTransaksiPenjualan::route('/{record}/edit'),
        ];
    }
}
