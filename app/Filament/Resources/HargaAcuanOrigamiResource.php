<?php

namespace App\Filament\Resources;

use BackedEnum;
use App\Filament\Resources\HargaAcuanOrigamiResource\Pages;
use App\Models\HargaAcuanOrigami;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class HargaAcuanOrigamiResource extends Resource
{
    protected static ?string $model = HargaAcuanOrigami::class;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-tag';
    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';
    protected static ?string $navigationLabel = 'Harga Acuan Origami';
    protected static ?string $modelLabel = 'Harga Acuan Origami';
    protected static ?string $pluralModelLabel = 'Harga Acuan Origami';

    public static function canCreate(): bool
    {
        // Harga Acuan baru hanya boleh dibuat lewat import awal atau lewat
        // approval di "Perbandingan Harga Stok Masuk" — supaya histori
        // periode berlaku (berlaku_mulai/berlaku_sampai) selalu konsisten
        // dan tidak ada baris aktif ganda untuk SKU yang sama.
        return false;
    }

    public static function canEdit($record = null): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')->sortable()->searchable(),
                TextColumn::make('produk.nama_produk')->label('Nama Produk')->limit(40)->searchable(),
                TextColumn::make('harga_acuan')->money('IDR')->sortable(),
                TextColumn::make('berlaku_mulai')->date('d M Y')->sortable(),
                TextColumn::make('berlaku_sampai')->date('d M Y')->placeholder('— (masih berlaku)')->sortable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('catatan')->limit(40)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('berlaku_mulai', 'desc')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Aktif')
                    ->falseLabel('Sudah berakhir')
                    ->native(false),
            ])
            ->actions([
                // Read-only — perubahan hanya lewat approval atau import.
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHargaAcuanOrigamis::route('/'),
        ];
    }
}
