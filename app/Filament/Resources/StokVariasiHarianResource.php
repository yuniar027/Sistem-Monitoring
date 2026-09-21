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
use Filament\Tables\Grouping\Group;
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

    /**
     * Urutan prioritas motif/warna — dipakai baik untuk menentukan label
     * kelompok (motifGroup) maupun urutan SQL (motifOrderCase), supaya
     * kedua logic itu SELALU konsisten satu sama lain (satu sumber,
     * bukan didefinisikan dua kali).
     */
    private const MOTIF_KEYWORDS = ['COKLAT', 'ABU', 'PINK', 'NAVY', 'SAGE', 'IKAN', 'KREM', 'CREM', 'PEACH', 'GOLD'];

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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['variasiGudang.barangGudang']);
    }

    private static function motifGroup(StokVariasiHarian $record): string
    {
        $barang = $record->variasiGudang?->barangGudang;
        $nama = strtoupper((string) $barang?->nama_barang);
        $motif = collect(self::MOTIF_KEYWORDS)
            ->first(fn (string $kata): bool => str_contains($nama, $kata)) ?? 'LAINNYA';
        $kategori = StokBarangGudangResource::kategoriOptions()[$barang?->kategori] ?? ($barang?->kategori ?? 'Tanpa Kategori');

        return "{$kategori} {$motif}";
    }

    /**
     * CASE SQL yang meniru persis urutan prioritas motifGroup() di atas
     * (keyword pertama yang cocok menang), supaya baris-baris dengan
     * label kelompok yang sama benar-benar bersebelahan di hasil query —
     * bukan cuma dikelompokkan tampilannya doang. Filament butuh data
     * yang sudah terurut oleh kunci grup, kalau tidak baris "Origami
     * Coklat" bisa pecah jadi beberapa blok terpisah (ini bug yang lagi
     * diperbaiki).
     */
    private static function motifOrderCase(): string
    {
        $whens = collect(self::MOTIF_KEYWORDS)
            ->map(fn (string $kata, int $i): string => "WHEN UPPER(stok_barang_gudang.nama_barang) LIKE '%{$kata}%' THEN " . ($i + 1))
            ->implode(' ');

        $fallback = count(self::MOTIF_KEYWORDS) + 1;

        return "CASE {$whens} ELSE {$fallback} END";
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultGroup(
                Group::make('motif_group')
                    ->label('Kelompok Motif/Warna')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false)
                    ->orderQueryUsing(
                        function (Builder $query, string $direction): Builder {
                            $direction = $direction === 'desc' ? 'desc' : 'asc';

                            return $query
                                ->join('stok_variasi_gudang', 'stok_variasi_gudang.id', '=', 'stok_variasi_harian.variasi_gudang_id')
                                ->join('stok_barang_gudang', 'stok_barang_gudang.id', '=', 'stok_variasi_gudang.barang_gudang_id')
                                // Wajib: tanpa select eksplisit ini, kolom `id` dari 3 tabel yang
                                // di-join bakal tabrakan dan merusak primary key hasil hydrate model.
                                ->select('stok_variasi_harian.*')
                                ->orderBy('stok_barang_gudang.kategori', $direction)
                                ->orderByRaw(static::motifOrderCase() . " {$direction}")
                                ->orderBy('stok_variasi_gudang.kode_variasi')
                                ->orderBy('stok_barang_gudang.nama_barang');
                        }
                    )
                    ->getKeyFromRecordUsing(
                        fn (StokVariasiHarian $record): string => static::motifGroup($record)
                    )
                    ->getTitleFromRecordUsing(
                        fn (StokVariasiHarian $record): string => static::motifGroup($record)
                    )
            )
            ->groupingSettingsHidden()
            ->columns([
                TextColumn::make('variasiGudang.barangGudang.nama_dasar')
                    ->label('Nama Barang'),
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