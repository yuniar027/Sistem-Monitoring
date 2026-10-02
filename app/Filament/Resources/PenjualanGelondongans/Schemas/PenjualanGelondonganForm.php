<?php

namespace App\Filament\Resources\PenjualanGelondongans\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PenjualanGelondonganForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Penjualan')
                    ->description('Catat total penjualan per minggu atau per bulan (angka besar, bukan per transaksi).')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        DatePicker::make('tanggal')
                            ->label('Tanggal (akhir periode)')
                            ->helperText('Tanggal ini menentukan masuk ke laporan bulan mana.')
                            ->default(now())
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required(),

                        TextInput::make('keterangan')
                            ->label('Keterangan periode')
                            ->placeholder('Contoh: Minggu ke-1 Oktober')
                            ->maxLength(255),

                        TextInput::make('channel')
                            ->label('Channel (opsional)')
                            ->placeholder('Contoh: Shopee, Toko, Semua channel')
                            ->maxLength(100),

                        TextInput::make('nominal_penjualan')
                            ->label('Total Penjualan')
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(1)
                            ->required(),

                        TextInput::make('nominal_hpp')
                            ->label('HPP / Modal Barang Terjual (opsional)')
                            ->helperText('Isi jika modal barang yang terjual pada periode ini diketahui. Biarkan 0 jika belum.')
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                    ]),
            ]);
    }
}
