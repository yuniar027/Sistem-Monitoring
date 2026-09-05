<?php

namespace App\Filament\Resources\StokBarangGudangResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class VariasiRelationManager extends RelationManager
{
    protected static string $relationship = 'variasi';
    protected static ?string $title = 'Variasi / Kemasan';
    protected static ?string $recordTitleAttribute = 'kode_variasi';

    protected static function isPabrik(): bool
    {
        return Auth::guard('gudang')->user()?->isPabrik() ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('kode_variasi')
                ->label('Kode Variasi')
                ->helperText('Contoh: K3SET, K18, K33, K39/30, K12')
                ->required()
                ->maxLength(50),
            TextInput::make('stok_aman')
                ->label('Stok Aman')
                ->required()
                ->numeric()
                ->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        if (static::isPabrik()) {
            return $table
                ->columns([
                    TextColumn::make('kode_variasi')->label('Kode Variasi')->sortable(),
                    TextColumn::make('stok_aman')->label('Stok Aman'),
                ]);
        }

        return $table
            ->columns([
                TextColumn::make('kode_variasi')->label('Kode Variasi')->sortable(),
                TextColumn::make('stok_aman')->label('Stok Aman'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}