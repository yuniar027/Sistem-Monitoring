<?php

namespace App\Filament\Resources\PenjualanGelondongans\Tables;

use App\Models\PenjualanGelondongan;
use App\Services\PenjualanGelondonganService;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PenjualanGelondongansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('keterangan')
                    ->label('Periode')
                    ->placeholder('—')
                    ->limit(40)
                    ->searchable(),

                TextColumn::make('channel')
                    ->label('Channel')
                    ->placeholder('—')
                    ->badge()
                    ->searchable(),

                TextColumn::make('nominal_penjualan')
                    ->label('Penjualan')
                    ->money('IDR')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->money('IDR')),

                TextColumn::make('nominal_hpp')
                    ->label('HPP')
                    ->money('IDR')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->money('IDR')),
            ])
            ->defaultSort('tanggal', 'desc')
            ->filters([
                Filter::make('rentang_tanggal')
                    ->label('Rentang Tanggal')
                    ->schema([
                        DatePicker::make('dari')->label('Dari')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('sampai')->label('Sampai')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, $tgl) => $q->whereDate('tanggal', '>=', $tgl))
                        ->when($data['sampai'] ?? null, fn (Builder $q, $tgl) => $q->whereDate('tanggal', '<=', $tgl))),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->modalHeading('Hapus catatan penjualan?')
                    ->modalDescription('Jurnal, Saldo Kas, dan laporan laba rugi yang terkait ikut dikoreksi.')
                    ->using(fn (PenjualanGelondongan $record) => app(PenjualanGelondonganService::class)->hapus($record)),
            ])
            ->emptyStateHeading('Belum ada catatan penjualan')
            ->emptyStateDescription('Klik "Tambah Penjualan" untuk mencatat penjualan pertama.');
    }
}
