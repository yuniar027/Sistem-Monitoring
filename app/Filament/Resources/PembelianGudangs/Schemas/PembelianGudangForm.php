<?php

namespace App\Filament\Resources\PembelianGudangs\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PembelianGudangForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Invoice')
                    ->schema([
                        TextInput::make('nomor_invoice')
                            ->label('Nomor Invoice')
                            ->required()
                            ->maxLength(255),

                        DatePicker::make('tanggal')
                            ->label('Tanggal Invoice')
                            ->required(),

                        TextInput::make('supplier')
                            ->label('Supplier / Pabrik')
                            ->maxLength(255)
                            ->nullable(),

                        Textarea::make('catatan')
                            ->label('Catatan')
                            ->rows(3)
                            ->nullable()
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }
}