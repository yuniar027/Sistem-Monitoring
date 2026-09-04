<?php

namespace App\Filament\Resources;

use BackedEnum;
use App\Filament\Resources\StokHarianGudangResource\Pages;
use App\Filament\Resources\StokHarianGudangResource\RelationManagers\AlokasiKhususRelationManager;
use App\Models\StokBarangGudang;
use App\Models\StokHarianGudang;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class StokHarianGudangResource extends Resource
{
    protected static ?string $model = StokHarianGudang::class;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static string|\UnitEnum|null $navigationGroup = 'Monitoring Stok Ringkas';
    protected static ?string $navigationLabel = 'Input Stok Harian';
    protected static ?string $modelLabel = 'Stok Harian';
    protected static ?string $pluralModelLabel = 'Stok Harian';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('barang_gudang_id')
                ->relationship('barangGudang', 'nama_barang')
                ->disabled()
                ->dehydrated(false),
            DatePicker::make('tanggal')
                ->disabled()
                ->dehydrated(false),
            TextInput::make('rak')
                ->label('Rak')
                ->required()
                ->numeric(),
            TextInput::make('input')
                ->label('Input')
                ->required()
                ->numeric(),
            TextInput::make('um_titip_pabrik')
                ->label('UM Titip Pabrik')
                ->numeric(),
            TextInput::make('stok_mentah_umma')
                ->label('Stok Mentah Umma')
                ->numeric(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('barangGudang.kategori')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => StokBarangGudangResource::kategoriOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => $state === StokBarangGudang::KATEGORI_ORIGAMI ? 'warning' : 'info'),
                TextColumn::make('barangGudang.kode_barang')->label('Kode')->sortable(),
                TextColumn::make('barangGudang.nama_barang')->label('Nama Barang')->searchable()->sortable(),
                TextColumn::make('tanggal')->date()->sortable(),
                TextColumn::make('rak')->label('Rak'),
                TextColumn::make('input')->label('Input'),
                TextColumn::make('stok_siap')
                    ->label('Stok Siap')
                    ->state(fn (StokHarianGudang $record) => $record->stok_siap),
                TextColumn::make('stok_akhir')
                    ->label('Stok Akhir')
                    ->state(fn (StokHarianGudang $record) => $record->stok_akhir)
                    ->color(fn (StokHarianGudang $record) => $record->stok_akhir < ($record->barangGudang?->stok_aman ?? 0) ? 'danger' : 'success')
                    ->weight('bold'),
                TextColumn::make('um_titip_pabrik')->label('UM Titip Pabrik'),
                TextColumn::make('stok_mentah_umma')->label('Stok Mentah Umma'),
            ])
            ->filters([
                SelectFilter::make('kategori')
                    ->relationship('barangGudang', 'kategori')
                    ->options(StokBarangGudangResource::kategoriOptions()),
                Filter::make('tanggal')
                    ->form([
                        DatePicker::make('dari')->label('Dari Tanggal')->default(today()),
                        DatePicker::make('sampai')->label('Sampai Tanggal')->default(today()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['dari'] ?? null, fn (Builder $q, $tanggal) => $q->whereDate('tanggal', '>=', Carbon::parse($tanggal)))
                            ->when($data['sampai'] ?? null, fn (Builder $q, $tanggal) => $q->whereDate('tanggal', '<=', Carbon::parse($tanggal)));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['dari'] ?? null) {
                            $indicators['dari'] = 'Dari: ' . Carbon::parse($data['dari'])->format('d M Y');
                        }
                        if ($data['sampai'] ?? null) {
                            $indicators['sampai'] = 'Sampai: ' . Carbon::parse($data['sampai'])->format('d M Y');
                        }

                        return $indicators;
                    }),
            ])
            ->defaultSort('tanggal', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            AlokasiKhususRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStokHarianGudangs::route('/'),
            'edit' => Pages\EditStokHarianGudang::route('/{record}/edit'),
        ];
    }
}