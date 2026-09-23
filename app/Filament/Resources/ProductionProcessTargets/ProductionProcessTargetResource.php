<?php

namespace App\Filament\Resources\ProductionProcessTargets;

use App\Filament\Resources\ProductionProcessTargets\Pages;
use App\Filament\Resources\StokBarangGudangResource;
use App\Models\ProductionProcess;
use App\Models\ProductionProcessTarget;
use App\Models\StokVariasiGudang;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class ProductionProcessTargetResource extends Resource
{
    protected static ?string $model = ProductionProcessTarget::class;
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string|\UnitEnum|null $navigationGroup = 'Monitoring Stok Ringkas';
    protected static ?string $navigationLabel = 'Target Produksi K';
    protected static ?string $modelLabel = 'Target Produksi K';
    protected static ?string $pluralModelLabel = 'Target Produksi K';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['productionProcess', 'variasiGudang']);
    }

    private static function variasiOptions(): array
    {
        return StokVariasiGudang::query()
            ->orderBy('kategori')
            ->orderBy('nama_dasar')
            ->orderBy('kode_variasi')
            ->get()
            ->mapWithKeys(function (StokVariasiGudang $variasi): array {
                $kategoriLabel = StokBarangGudangResource::kategoriOptions()[$variasi->kategori] ?? ($variasi->kategori ?? '—');
                $namaDasar = $variasi->nama_dasar ?? $variasi->barangGudang?->nama_barang ?? '—';

                return [$variasi->id => "{$kategoriLabel} — {$namaDasar} — {$variasi->kode_variasi}"];
            })
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('production_process_id')
                ->label('Proses K')
                ->options(
                    fn (): array => ProductionProcess::query()
                        ->orderBy('kode_proses')
                        ->pluck('kode_proses', 'id')
                        ->all()
                )
                ->searchable()
                ->preload()
                ->required(),

            Select::make('variasi_gudang_id')
                ->label('Variasi Target')
                ->helperText('Variasi yang jadi hasil/output dari proses K ini.')
                ->options(fn (): array => static::variasiOptions())
                ->searchable()
                ->preload()
                ->required()
                ->unique(
                    table: 'production_process_targets',
                    column: 'variasi_gudang_id',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('production_process_id', $get('production_process_id')),
                )
                ->validationMessages([
                    'unique' => 'Proses K ini sudah punya target untuk variasi tersebut.',
                ]),

            TextInput::make('multiplier')
                ->label('Multiplier')
                ->helperText('Output per 1 unit source. Contoh: 1 unit source menghasilkan 3 pcs variasi -> isi 3.')
                ->numeric()
                ->minValue(0.01)
                ->step(0.01)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('productionProcess.kode_proses')
                    ->label('Proses K')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('variasiGudang.kategori')
                    ->label('Kategori')
                    ->formatStateUsing(fn (?string $state): string => $state ? (StokBarangGudangResource::kategoriOptions()[$state] ?? $state) : '—')
                    ->sortable(),
                TextColumn::make('variasiGudang.nama_dasar')
                    ->label('Nama Dasar')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('variasiGudang.kode_variasi')
                    ->label('Kode Variasi')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('multiplier')
                    ->label('Multiplier')
                    ->weight('bold'),
            ])
            ->filters([
                SelectFilter::make('production_process_id')
                    ->label('Proses K')
                    ->options(
                        fn (): array => ProductionProcess::query()
                            ->orderBy('kode_proses')
                            ->pluck('kode_proses', 'id')
                            ->all()
                    ),
            ])
            ->defaultSort('production_process_id');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageProductionProcessTargets::route('/'),
        ];
    }
}