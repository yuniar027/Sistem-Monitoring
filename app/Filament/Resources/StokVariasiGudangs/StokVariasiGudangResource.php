<?php

namespace App\Filament\Resources\StokVariasiGudangs;

use App\Filament\Resources\StokVariasiGudangs\Pages;
use App\Filament\Resources\StokBarangGudangResource;
use App\Models\StokBarangGudang;
use App\Models\StokVariasiGudang;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StokVariasiGudangResource extends Resource
{
    protected static ?string $model = StokVariasiGudang::class;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-tag';
    protected static string|\UnitEnum|null $navigationGroup = 'Monitoring Stok Ringkas';
    protected static ?string $navigationLabel = 'Master Variasi Gudang';
    protected static ?string $modelLabel = 'Variasi Gudang';
    protected static ?string $pluralModelLabel = 'Variasi Gudang';

    public static function getEloquentQuery(): Builder
    {
        // barangGudang tetap di-load buat baris lama (sebelum migration) yang
        // belum py kategori/nama_dasar sendiri -- lihat fallback di kolom tabel.
        return parent::getEloquentQuery()->with('barangGudang');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('kategori')
                ->label('Kategori')
                ->options(fn (): array => StokBarangGudangResource::kategoriOptions())
                ->required()
                ->live()
                ->afterStateUpdated(fn (\Filament\Schemas\Components\Utilities\Set $set) => $set('nama_dasar', null)),

            Select::make('nama_dasar')
                ->label('Nama Dasar (Motif)')
                ->helperText('Grup barang yang sama tapi beda ukuran (BT/PD/PJ) sudah otomatis digabung di sini.')
                ->options(function (\Filament\Schemas\Components\Utilities\Get $get): array {
                    $kategori = $get('kategori');

                    if (! $kategori) {
                        return [];
                    }

                    return StokBarangGudang::query()
                        ->where('kategori', $kategori)
                        ->get()
                        ->pluck('nama_dasar', 'nama_dasar')
                        ->unique()
                        ->sort()
                        ->all();
                })
                ->required()
                ->searchable()
                ->disabled(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => ! $get('kategori')),

            TextInput::make('kode_variasi')
                ->label('Kode Variasi')
                ->helperText('Contoh: 3S BTG, 3S PD, 12PCS — harus sama persis dengan yang nanti diketik di Alokasi Khusus (tidak peduli besar/kecil huruf & spasi berlebih).')
                ->required()
                ->unique(
                    table: 'stok_variasi_gudang',
                    column: 'kode_variasi',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (\Illuminate\Validation\Rules\Unique $rule, \Filament\Schemas\Components\Utilities\Get $get) => $rule
                        ->where('kategori', $get('kategori'))
                        ->where('nama_dasar', $get('nama_dasar'))
                ),

            TextInput::make('stok_aman')
                ->label('Stok Aman')
                ->numeric()
                ->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kategori')
                    ->label('Kategori')
                    ->formatStateUsing(fn (?string $state): string => $state ? (StokBarangGudangResource::kategoriOptions()[$state] ?? $state) : '—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nama_dasar')
                    ->label('Nama Dasar (Motif)')
                    // fallback ke nama barang lama untuk baris sebelum migration
                    // yang belum sempat di-backfill (harusnya sudah tidak ada lagi)
                    ->formatStateUsing(fn (?string $state, StokVariasiGudang $record): string => $state ?? $record->barangGudang?->nama_barang ?? '—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('kode_variasi')
                    ->label('Kode Variasi')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('stok_aman')
                    ->label('Stok Aman'),
            ])
            ->filters([
                Filter::make('kategori')
                    ->form([
                        Select::make('kategori')
                            ->options(StokBarangGudangResource::kategoriOptions())
                            ->placeholder('Semua kategori'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['kategori'] ?? null,
                            fn (Builder $q, $kategori) => $q->where('kategori', $kategori)
                        );
                    }),
            ])
            ->defaultSort('nama_dasar');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageStokVariasiGudangs::route('/'),
        ];
    }
}