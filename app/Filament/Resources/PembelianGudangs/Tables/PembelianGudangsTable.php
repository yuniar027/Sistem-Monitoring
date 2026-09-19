<?php

namespace App\Filament\Resources\PembelianGudangs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;

class PembelianGudangsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nomor_invoice')
                    ->label('Nomor Invoice')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('supplier')
                    ->label('Supplier / Pabrik')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('detail_count')
                    ->label('Jumlah Jenis Barang')
                    ->counts('detail')
                    ->sortable(),

                TextColumn::make('detail_sum_kuantitas')
                    ->label('Total Kuantitas')
                    ->sum('detail', 'kuantitas')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),

                TextColumn::make('detail_sum_nilai_invoice')
                    ->label('Total Nilai Invoice')
                    ->sum('detail', 'nilai_invoice')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('status_harga')
                    ->label('Status Harga')
                    ->state(function ($record) {
                        $details = $record->detail;

                        if ($details->isEmpty()) {
                            return '—';
                        }

                        $counts = $details
                            ->groupBy('kategori_perbandingan')
                            ->map->count();

                        $parts = [];

                        foreach (['naik', 'turun', 'sama'] as $status) {
                            $jumlah = $counts->get($status, 0);

                            if ($jumlah > 0) {
                                $parts[] = strtoupper($status) . ' ' . $jumlah;
                            }
                        }

                        return implode(' · ', $parts);
                    })
                    ->badge()
                    ->color(function ($record): string {
                        $details = $record->detail;

                        if ($details->contains('kategori_perbandingan', 'naik')) {
                            return 'danger';
                        }

                        if ($details->contains('kategori_perbandingan', 'turun')) {
                            return 'success';
                        }

                        return 'gray';
                    })
                    ->weight(FontWeight::Bold),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}