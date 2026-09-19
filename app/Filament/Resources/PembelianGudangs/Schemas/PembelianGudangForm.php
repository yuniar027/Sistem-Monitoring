<?php

namespace App\Filament\Resources\PembelianGudangs\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
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
                            ->default(now())
                            ->required(),

                        TextInput::make('supplier')
                            ->label('Supplier / Pabrik')
                            ->maxLength(255)
                            ->nullable(),

                        FileUpload::make('file_invoice')
                            ->label('File Excel Invoice')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                                'text/csv',
                            ])
                            ->disk('local')
                            ->directory('invoice-gudang')
                            ->required()
                            ->columnSpanFull(),

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