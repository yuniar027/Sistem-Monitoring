<?php

namespace App\Filament\Resources\BiayaOperasionals\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BiayaOperasionalInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Biaya')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('kategori')
                            ->label('Kategori')
                            ->formatStateUsing(fn ($state) => config('kategori_biaya.' . $state, $state))
                            ->badge(),

                        TextEntry::make('tanggal')
                            ->label('Tanggal')
                            ->date('d F Y'),

                        TextEntry::make('nominal')
                            ->label('Nominal')
                            ->money('IDR')
                            ->weight('bold'),

                        TextEntry::make('created_at')
                            ->label('Dicatat pada')
                            ->dateTime('d/m/Y H:i'),

                        TextEntry::make('keterangan')
                            ->label('Keterangan')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
