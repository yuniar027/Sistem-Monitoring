<?php

namespace App\Filament\Resources\BiayaOperasionals\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BiayaOperasionalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Biaya')
                    ->description('Catat pengeluaran operasional di luar pembelian barang.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('kategori')
                            ->label('Kategori')
                            ->options(config('kategori_biaya'))
                            ->placeholder('Pilih kategori')
                            ->native(false)
                            ->required(),

                        DatePicker::make('tanggal')
                            ->label('Tanggal')
                            ->default(now())
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required(),

                        TextInput::make('nominal')
                            ->label('Nominal')
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(1)
                            ->required(),

                        Textarea::make('keterangan')
                            ->label('Keterangan (opsional)')
                            ->rows(3)
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
