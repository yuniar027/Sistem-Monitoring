<?php

namespace App\Filament\Pages;

use App\Models\StokPaket;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class LaporanReject extends Page implements HasTable
{
    use InteractsWithTable;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static string|\UnitEnum|null $navigationGroup = 'Stok & Gudang';
    protected static ?string $navigationLabel = 'Laporan Reject';
    protected static ?string $title = 'Laporan Reject Bahan Baku';
    protected string $view = 'filament.pages.laporan-reject';

    public function table(Table $table): Table
    {
        return $table
            ->query(StokPaket::where('status_reject', 'melebihi_batas'))
            ->columns([
                TextColumn::make('tanggal_dibuat')->label('Tanggal')->date(),
                TextColumn::make('sku')->label('SKU')->searchable(),
                TextColumn::make('jumlah_target')->label('Target'),
                TextColumn::make('jumlah_paket')->label('Jadi'),
                TextColumn::make('jumlah_reject')->label('Reject')->color('danger')->weight('bold'),
                TextColumn::make('persentase_reject')->label('% Reject')->suffix('%')->color('danger'),
            ])
            ->defaultSort('tanggal_dibuat', 'desc');
    }
}