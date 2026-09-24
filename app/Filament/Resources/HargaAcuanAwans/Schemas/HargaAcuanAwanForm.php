<?php

namespace App\Filament\Resources\HargaAcuanAwans\Schemas;

use App\Models\StokBarangGudang;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class HargaAcuanAwanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('barang_gudang_id')
                    ->label('Barang Awan')
                    ->relationship(
                        name: 'barangGudang',
                        titleAttribute: 'nama_barang',
                        modifyQueryUsing: fn ($query) => $query
                            ->where('kategori', StokBarangGudang::KATEGORI_AWAN)
                            ->orderBy('nama_barang')
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->unique(ignoreRecord: true),

                TextInput::make('harga_acuan')
                    ->label('Harga Acuan')
                    ->numeric()
                    ->prefix('Rp')
                    ->minValue(0)
                    ->required(),

                DatePicker::make('berlaku_mulai')
                    ->label('Berlaku Mulai')
                    ->default(now())
                    ->required(),

                DatePicker::make('berlaku_sampai')
                    ->label('Berlaku Sampai')
                    ->nullable()
                    ->after('berlaku_mulai'),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),

                Textarea::make('catatan')
                    ->label('Catatan')
                    ->rows(3)
                    ->nullable()
                    ->columnSpanFull(),
            ]);
    }
}