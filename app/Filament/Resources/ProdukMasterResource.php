<?php

namespace App\Filament\Resources;

use BackedEnum;
use App\Filament\Resources\ProdukMasterResource\Pages;
use App\Models\ProdukMaster;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Form;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Closure;

class ProdukMasterResource extends Resource
{
        protected static ?string $model = ProdukMaster::class;
        protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';
        protected static string|\UnitEnum|null $navigationGroup = 'Produk & Bahan Baku';
        protected static ?string $navigationLabel = 'Produk Master';
        protected static ?string $modelLabel = 'Produk Master';
        protected static ?string $pluralModelLabel = 'Produk Master';

    /**
     * Ambil angka di paling depan SKU (mengabaikan spasi/strip di awal).
     * Return null kalau SKU tidak diawali angka sama sekali (format tidak beraturan).
     */
    protected static function angkaDepanSku(?string $sku): ?string
    {
        if (! $sku) {
            return null;
        }

        if (preg_match('/^(\d+)/', trim($sku), $m)) {
            return $m[1];
        }

        return null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('sku')
                ->required()
                ->unique(ignoreRecord: true)
                ->live(onBlur: true)
                ->helperText(function (Get $get) {
                    $angka = self::angkaDepanSku($get('sku'));

                    if ($angka === null) {
                        return 'Format SKU tidak diawali angka — tipe produk tidak bisa dicek otomatis, isi manual dengan hati-hati.';
                    }

                    $seharusnya = $angka === '1' ? 'Simple' : 'Rakitan';

                    return "Angka depan SKU: {$angka} → seharusnya bertipe: {$seharusnya}";
                }),

            Select::make('tipe_produk')
                ->options([
                    'simple' => 'Simple',
                    'rakitan' => 'Rakitan',
                ])
                ->required()
                ->live()
                ->rules([
                    function (Get $get) {
                        return function (string $attribute, $value, Closure $fail) use ($get) {
                            $angka = self::angkaDepanSku($get('sku'));

                            if ($angka === null) {
                                // Format SKU tidak beraturan (tanpa angka depan) — tidak bisa divalidasi otomatis, dilewati.
                                return;
                            }

                            if ($angka === '1' && $value !== 'simple') {
                                $fail('SKU dengan angka depan "1" harus bertipe Simple (barang tunggal, dijual satuan, bukan bahan produk lain).');
                            }

                            if ($angka !== '1' && $value !== 'rakitan') {
                                $fail("SKU dengan angka depan \"{$angka}\" harus bertipe Rakitan — walau isinya cuma 1 jenis barang yang diulang (misal bedong x{$angka}), tetap wajib punya resep supaya bahan bakunya bisa dipakai bareng produk lain.");
                            }
                        };
                    },
                ]),

            TextInput::make('nama_produk')->required(),
            TextInput::make('satuan_jual')->required(),
            TextInput::make('satuan_beli')->required(),
            TextInput::make('isi_per_satuan_beli')->required()->numeric()->default(1),
            TextInput::make('kategori')->nullable(),
            TextInput::make('harga_modal_default')->numeric()->nullable()->prefix('Rp'),
            TextInput::make('harga_jual_referensi')->numeric()->nullable()->prefix('Rp'),
            TextInput::make('target_stok_minimum')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')->sortable()->searchable(),
                TextColumn::make('nama_produk')->limit(50)->searchable(),
                TextColumn::make('tipe_produk')->badge(),
                TextColumn::make('satuan_jual'),
                TextColumn::make('satuan_beli'),
                TextColumn::make('isi_per_satuan_beli'),
                TextColumn::make('harga_jual_referensi')->money('IDR')->sortable(),
                TextColumn::make('target_stok_minimum'),
            ])
            ->filters([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProdukMasters::route('/'),
            'create' => Pages\CreateProdukMaster::route('/create'),
            'edit' => Pages\EditProdukMaster::route('/{record}/edit'),
        ];
    }
}