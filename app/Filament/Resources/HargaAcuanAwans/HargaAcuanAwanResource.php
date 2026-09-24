<?php

namespace App\Filament\Resources\HargaAcuanAwans;

use App\Filament\Clusters\HargaAcuanProduk;
use App\Filament\Resources\HargaAcuanAwans\Pages\CreateHargaAcuanAwan;
use App\Filament\Resources\HargaAcuanAwans\Pages\EditHargaAcuanAwan;
use App\Filament\Resources\HargaAcuanAwans\Pages\ListHargaAcuanAwans;
use App\Filament\Resources\HargaAcuanAwans\Schemas\HargaAcuanAwanForm;
use App\Filament\Resources\HargaAcuanAwans\Tables\HargaAcuanAwansTable;
use App\Models\HargaAcuanOrigami;
use App\Models\StokBarangGudang;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class HargaAcuanAwanResource extends Resource
{
    protected static ?string $cluster = HargaAcuanProduk::class;

    protected static ?string $navigationLabel = 'Awan';

    protected static ?string $modelLabel = 'harga acuan awan';

    protected static ?string $pluralModelLabel = 'Harga Acuan Awan';

    protected static ?int $navigationSort = 2;

    // Sengaja tetap pakai model & tabel HargaAcuanOrigami -- satu tabel
    // harga_acuan_origami dipakai bareng untuk kategori Origami & Awan,
    // dibedakan lewat filter kategori barang di getEloquentQuery().
    protected static ?string $model = HargaAcuanOrigami::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function canAccess(): bool
    {
        return Auth::guard('gudang')->user()?->bisaAksesKeuangan() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas(
            'barangGudang',
            fn (Builder $query) => $query->where('kategori', StokBarangGudang::KATEGORI_AWAN)
        );
    }

    public static function form(Schema $schema): Schema
    {
        return HargaAcuanAwanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HargaAcuanAwansTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHargaAcuanAwans::route('/'),
            'create' => CreateHargaAcuanAwan::route('/create'),
            'edit' => EditHargaAcuanAwan::route('/{record}/edit'),
        ];
    }
}