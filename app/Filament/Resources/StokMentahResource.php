<?php

namespace App\Filament\Resources;

use BackedEnum;
use App\Filament\Resources\StokMentahResource\Pages;
use App\Models\StokMentah;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Resources\Resource;

class StokMentahResource extends Resource
{
    protected static ?string $model = StokMentah::class;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-inbox-stack';
    protected static ?string $navigationLabel = 'Stok Mentah';
    protected static ?string $modelLabel = 'Stok Mentah';
    protected static ?string $pluralModelLabel = 'Stok Mentah';

    protected static bool $shouldRegisterNavigation = true;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')->sortable(),
                TextColumn::make('produk.nama_produk')->label('Nama Produk'),
                TextColumn::make('kuantitas_tersedia')->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('kuantitas_tersedia', 'asc')
            ->filters([
                //
            ])
            ->actions([
                // No edit/delete/view actions — read-only
            ])
            ->bulkActions([
                // No bulk actions
            ])
            ->emptyStateActions([
                // No create action — data is managed via Services
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStokMentahs::route('/'),
        ];
    }
}
