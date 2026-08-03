<?php

namespace App\Filament\Resources;

use BackedEnum;
use App\Filament\Resources\JurnalUmumResource\Pages;
use App\Models\JurnalUmum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Table;

class JurnalUmumResource extends Resource
{
    protected static ?string $model = JurnalUmum::class;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-book-open';
    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';
    protected static ?string $navigationLabel = 'Jurnal Umum';
    protected static ?string $modelLabel = 'Jurnal Umum';
    protected static ?string $pluralModelLabel = 'Jurnal Umum';

    /**
     * Nama akun yang enak dibaca, bukan cuma kode angka.
     * Bisa diakses static supaya dipakai juga di Widget ringkasan.
     */
    public static function namaAkun(): array
    {
        return [
            '1100' => 'Kas',
            '1200' => 'Piutang Usaha',
            '1300' => 'Persediaan',
            '2100' => 'Hutang Usaha',
            '3100' => 'Modal',
            '4100' => 'Penjualan',
            '5100' => 'HPP (Harga Pokok Penjualan)',
            '6100' => 'Biaya Operasional',
        ];
    }

    public static function namaSumber(): array
    {
        return [
            'stok_masuk' => 'Stok Masuk',
            'bahan_baku_masuk' => 'Bahan Baku Masuk',
            'transaksi_penjualan' => 'Transaksi Penjualan',
        ];
    }

    public static function canCreate(): bool
    {
        // Jurnal dibuat otomatis oleh sistem, tidak boleh diinput manual
        // supaya buku besar selalu konsisten dengan transaksi aslinya.
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal')->date('d M Y')->sortable(),
                TextColumn::make('kode_akun')
                    ->label('Akun')
                    ->formatStateUsing(fn (string $state): string => (self::namaAkun()[$state] ?? $state) . " ({$state})")
                    ->badge(),
                TextColumn::make('keterangan')->limit(60)->searchable(),
                TextColumn::make('debit')->money('IDR')->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),
                TextColumn::make('kredit')->money('IDR')->sortable()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('sumber_tipe')
                    ->label('Sumber Transaksi')
                    ->formatStateUsing(fn (string $state): string => self::namaSumber()[$state] ?? $state)
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('kode_akun')
                    ->label('Akun')
                    ->options(self::namaAkun()),
                SelectFilter::make('sumber_tipe')
                    ->label('Sumber Transaksi')
                    ->options(self::namaSumber()),
                Filter::make('tanggal')
                    ->schema([
                        DatePicker::make('dari_tanggal'),
                        DatePicker::make('sampai_tanggal'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['dari_tanggal'] ?? null, fn ($q, $date) => $q->whereDate('tanggal', '>=', $date))
                            ->when($data['sampai_tanggal'] ?? null, fn ($q, $date) => $q->whereDate('tanggal', '<=', $date));
                    }),
            ])
            ->defaultSort('tanggal', 'desc')
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJurnalUmums::route('/'),
        ];
    }
}
