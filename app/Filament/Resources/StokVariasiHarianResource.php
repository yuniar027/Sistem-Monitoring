<?php

namespace App\Filament\Resources;

use BackedEnum;
use App\Filament\Resources\StokVariasiHarianResource\Pages;
use App\Models\StokVariasiHarian;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class StokVariasiHarianResource extends Resource
{
    protected static ?string $model = StokVariasiHarian::class;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-squares-2x2';
    protected static string|\UnitEnum|null $navigationGroup = 'Monitoring Stok Ringkas';
    protected static ?string $navigationLabel = 'Input Variasi Harian';
    protected static ?string $modelLabel = 'Variasi Harian';
    protected static ?string $pluralModelLabel = 'Variasi Harian';

    public static function isPabrik(): bool
    {
        return \Illuminate\Support\Facades\Auth::guard('gudang')->user()?->isPabrik() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        $readOnly = static::isPabrik();

        return $schema->schema([
            DatePicker::make('tanggal')
                ->disabled()
                ->dehydrated(false),
            TextInput::make('stok_awal')
                ->label('Stok Awal')
                ->required()
                ->numeric()
                ->disabled($readOnly),
            TextInput::make('input')
                ->label('Input')
                ->required()
                ->numeric()
                ->disabled($readOnly),
            TextInput::make('out')
                ->label('Out')
                ->required()
                ->numeric()
                ->disabled($readOnly),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('variasiGudang.barangGudang.kategori')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => StokBarangGudangResource::kategoriOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => $state === \App\Models\StokBarangGudang::KATEGORI_ORIGAMI ? 'warning' : 'info'),
                TextColumn::make('variasiGudang.barangGudang.kode_barang')->label('Kode Barang')->sortable(),
                TextColumn::make('variasiGudang.barangGudang.nama_barang')->label('Nama Barang')->searchable()->sortable(),
                TextColumn::make('variasiGudang.kode_variasi')->label('Kode Variasi')->sortable(),
                TextColumn::make('tanggal')->date()->sortable(),
                TextColumn::make('stok_awal')->label('Stok Awal'),
                TextColumn::make('input')->label('Input'),
                TextColumn::make('stok_hasil')
                    ->label('Stok Hasil')
                    ->state(fn (StokVariasiHarian $record) => $record->stok_hasil),
                TextColumn::make('out')->label('Out'),
                TextColumn::make('sisa')
                    ->label('Sisa')
                    ->state(fn (StokVariasiHarian $record) => $record->sisa)
                    ->weight('bold'),
            ])
            ->filters([
                Filter::make('kategori')
                    ->form([
                        \Filament\Forms\Components\Select::make('kategori')
                            ->options(StokBarangGudangResource::kategoriOptions())
                            ->placeholder('Semua kategori'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['kategori'] ?? null,
                            fn (Builder $q, $kategori) => $q->whereHas(
                                'variasiGudang.barangGudang',
                                fn (Builder $q2) => $q2->where('kategori', $kategori)
                            )
                        );
                    }),
                Filter::make('tanggal')
                    ->form([
                        DatePicker::make('dari')->label('Dari Tanggal')->default(today()),
                        DatePicker::make('sampai')->label('Sampai Tanggal')->default(today()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['dari'] ?? null, fn (Builder $q, $tanggal) => $q->whereDate('tanggal', '>=', Carbon::parse($tanggal)))
                            ->when($data['sampai'] ?? null, fn (Builder $q, $tanggal) => $q->whereDate('tanggal', '<=', Carbon::parse($tanggal)));
                    }),
            ])
            ->defaultSort('tanggal', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStokVariasiHarians::route('/'),
            'edit' => Pages\EditStokVariasiHarian::route('/{record}/edit'),
        ];
    }
}