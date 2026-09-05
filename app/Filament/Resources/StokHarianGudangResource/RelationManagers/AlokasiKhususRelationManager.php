<?php

namespace App\Filament\Resources\StokHarianGudangResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AlokasiKhususRelationManager extends RelationManager
{
    protected static string $relationship = 'alokasiKhusus';
    protected static ?string $title = 'Alokasi Khusus (Kolom K)';
    protected static ?string $recordTitleAttribute = 'kode_alokasi';

    protected static function isPabrik(): bool
    {
        return Auth::guard('gudang')->user()?->isPabrik() ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('kode_alokasi')
                ->label('Kode Alokasi')
                ->helperText('Contoh: K 3 SET, K 18, K 33, K 39/30, K 12, K 27, K 48')
                ->required()
                ->maxLength(50),
            TextInput::make('kuantitas')
                ->required()
                ->numeric()
                ->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        // Pabrik cuma boleh LIHAT, nggak boleh tambah/edit/hapus
        if (static::isPabrik()) {
            return $table
                ->columns([
                    TextColumn::make('kode_alokasi')->label('Kode Alokasi'),
                    TextColumn::make('kuantitas'),
                ]);
        }

        return $table
            ->columns([
                TextColumn::make('kode_alokasi')->label('Kode Alokasi'),
                TextColumn::make('kuantitas'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // isi otomatis barang_gudang_id & tanggal dari record induk (StokHarianGudang)
                        $data['barang_gudang_id'] = $this->getOwnerRecord()->barang_gudang_id;
                        $data['tanggal'] = $this->getOwnerRecord()->tanggal;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}