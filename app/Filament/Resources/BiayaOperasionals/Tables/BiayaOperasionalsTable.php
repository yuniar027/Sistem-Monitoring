<?php

namespace App\Filament\Resources\BiayaOperasionals\Tables;

use App\Models\BiayaOperasional;
use App\Services\BiayaOperasionalService;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BiayaOperasionalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('kategori')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn ($state) => config('kategori_biaya.' . $state, $state))
                    ->sortable(),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->placeholder('—')
                    ->limit(50)
                    ->tooltip(fn (BiayaOperasional $record) => $record->keterangan)
                    ->searchable(),

                TextColumn::make('nominal')
                    ->label('Nominal')
                    ->money('IDR')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->money('IDR')),
            ])
            ->defaultSort('tanggal', 'desc')
            ->filters([
                SelectFilter::make('kategori')
                    ->label('Kategori')
                    ->options(config('kategori_biaya')),

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
                    ->modalHeading('Hapus biaya operasional?')
                    ->modalDescription('Jurnal dan Saldo Kas yang terkait biaya ini ikut dikoreksi.')
                    ->using(fn (BiayaOperasional $record) => app(BiayaOperasionalService::class)->hapusBiaya($record)),
            ])
            ->emptyStateHeading('Belum ada biaya operasional')
            ->emptyStateDescription('Klik "Tambah Biaya" untuk mencatat biaya pertama.');
    }
}
