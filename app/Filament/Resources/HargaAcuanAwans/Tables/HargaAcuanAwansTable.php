<?php

namespace App\Filament\Resources\HargaAcuanAwans\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class HargaAcuanAwansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('barangGudang.nama_barang')
                    ->label('Barang')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('barangGudang.kode_barang')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('harga_acuan')
                    ->label('Harga Acuan')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('berlaku_mulai')
                    ->label('Berlaku Mulai')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('berlaku_sampai')
                    ->label('Berlaku Sampai')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Aktif'),

                TextColumn::make('catatan')
                    ->label('Catatan')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->catatan),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Tidak Aktif')
                    ->default(true),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}