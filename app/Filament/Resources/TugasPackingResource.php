<?php

namespace App\Filament\Resources;

use BackedEnum;
use App\Filament\Resources\TugasPackingResource\Pages;
use App\Models\ProdukMaster;
use App\Models\TugasPacking;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TugasPackingResource extends Resource
{
    protected static ?string $model = TugasPacking::class;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static string|\UnitEnum|null $navigationGroup = 'Stok & Gudang';
    protected static ?string $navigationLabel = 'Tugas Packing';
    protected static ?string $modelLabel = 'Tugas Packing';
    protected static ?string $pluralModelLabel = 'Tugas Packing';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('sku')
                ->options(fn () => ProdukMaster::orderBy('sku')->pluck('sku', 'sku')->toArray())
                ->searchable()
                ->required(),

            Select::make('channel_tujuan')
                ->options([
                    'shopee' => 'Shopee',
                    'tiktok' => 'TikTok',
                ])
                ->required(),

            TextInput::make('kuantitas')
                ->numeric()
                ->required()
                ->minValue(1),

            TextInput::make('ditugaskan_ke')
                ->label('Nomor Urut')
                ->numeric()
                ->nullable(),

            Select::make('status')
                ->options([
                    'belum_dikerjakan' => 'Belum Dikerjakan',
                    'dikerjakan' => 'Dikerjakan',
                    'selesai' => 'Selesai',
                ])
                ->default('belum_dikerjakan')
                ->required(),

            DatePicker::make('tanggal_dibuat')
                ->required()
                ->default(now()),

            DatePicker::make('tanggal_selesai')
                ->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')->searchable(),
                TextColumn::make('produk.nama_produk')->label('Nama Produk')->limit(40),
                TextColumn::make('channel_tujuan')->badge(),
                TextColumn::make('kuantitas'),
                TextColumn::make('ditugaskan_ke')->label('Nomor Urut')->placeholder('— Belum ditugaskan —'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'selesai' => 'success',
                        'dikerjakan' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'belum_dikerjakan' => 'Belum Dikerjakan',
                        'dikerjakan' => 'Dikerjakan',
                        'selesai' => 'Selesai',
                        default => $state,
                    }),
                TextColumn::make('tanggal_dibuat')->date('d M Y'),
                TextColumn::make('tanggal_selesai')->date('d M Y')->placeholder('—'),
            ])
            ->defaultSort('tanggal_dibuat', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTugasPackings::route('/'),
            'create' => Pages\CreateTugasPacking::route('/create'),
            'edit' => Pages\EditTugasPacking::route('/{record}/edit'),
        ];
    }
}
