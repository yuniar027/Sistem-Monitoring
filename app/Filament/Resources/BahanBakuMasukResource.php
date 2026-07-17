<?php

namespace App\Filament\Resources;

use BackedEnum;
use App\Filament\Resources\BahanBakuMasukResource\Pages;
use App\Models\BahanBakuMasuk;
use App\Models\BahanBaku;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class BahanBakuMasukResource extends Resource
{
    protected static ?string $model = BahanBakuMasuk::class;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationLabel = 'Bahan Baku Masuk';
    protected static ?string $modelLabel = 'Bahan Baku Masuk';
    protected static ?string $pluralModelLabel = 'Bahan Baku Masuk';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            DatePicker::make('tanggal')->required()->default(now()),
            Select::make('bahan_baku_id')
                ->options(fn () => BahanBaku::orderBy('kode_bahan')->pluck('nama_bahan', 'id')->toArray())
                ->searchable()
                ->required()
                ->label('Bahan Baku'),
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
                TextColumn::make('bahanBaku.kode_bahan')->label('Kode Bahan')->sortable(),
                TextColumn::make('bahanBaku.nama_bahan')->label('Nama Bahan')->sortable(),
                TextColumn::make('vendor'),
                TextColumn::make('kuantitas'),
                TextColumn::make('harga_satuan'),
                TextColumn::make('total_nominal'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBahanBakuMasuks::route('/'),
            'create' => Pages\CreateBahanBakuMasuk::route('/create'),
            'edit' => Pages\EditBahanBakuMasuk::route('/{record}/edit'),
        ];
    }
}
