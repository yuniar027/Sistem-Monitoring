<?php

namespace App\Filament\Resources\PenjualanGelondongans\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PenjualanGelondonganInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Penjualan')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('tanggal')
                            ->label('Tanggal (akhir periode)')
                            ->date('d F Y'),

                        TextEntry::make('keterangan')
                            ->label('Keterangan periode')
                            ->placeholder('—'),

                        TextEntry::make('channel')
                            ->label('Channel')
                            ->placeholder('—'),

                        TextEntry::make('nominal_penjualan')
                            ->label('Total Penjualan')
                            ->money('IDR')
                            ->weight('bold'),

                        TextEntry::make('nominal_hpp')
                            ->label('HPP / Modal Barang Terjual')
                            ->money('IDR'),

                        TextEntry::make('laba_kotor')
                            ->label('Laba Kotor')
                            ->state(fn ($record) => $record->laba_kotor)
                            ->money('IDR'),

                        TextEntry::make('created_at')
                            ->label('Dicatat pada')
                            ->dateTime('d/m/Y H:i'),
                    ]),
            ]);
    }
}
