<?php

namespace App\Filament\Resources\ResepPaketItemResource\Pages;

use App\Filament\Resources\ResepPaketItemResource;
use App\Models\BahanBaku;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class EditResepPaketItem extends EditRecord
{
    protected static string $resource = ResepPaketItemResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('sku')->required(),
            Select::make('bahan_baku_id')
                ->label('Bahan Baku')
                ->options(fn () => BahanBaku::orderBy('kode_bahan')->pluck('nama_bahan', 'id')->toArray())
                ->searchable()
                ->required(),
            TextInput::make('kuantitas_per_paket')
                ->label('Kuantitas per Paket')
                ->numeric()
                ->minValue(1)
                ->required(),
        ]);
    }
}