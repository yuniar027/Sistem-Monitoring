<?php

namespace App\Filament\Resources\HargaAcuanOrigamis;

use App\Filament\Clusters\HargaAcuanProduk;
use App\Filament\Resources\HargaAcuanOrigamis\Pages\CreateHargaAcuanOrigami;
use App\Filament\Resources\HargaAcuanOrigamis\Pages\EditHargaAcuanOrigami;
use App\Filament\Resources\HargaAcuanOrigamis\Pages\ListHargaAcuanOrigamis;
use App\Filament\Resources\HargaAcuanOrigamis\Schemas\HargaAcuanOrigamiForm;
use App\Filament\Resources\HargaAcuanOrigamis\Tables\HargaAcuanOrigamisTable;
use App\Models\HargaAcuanOrigami;
use App\Models\StokBarangGudang;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class HargaAcuanOrigamiResource extends Resource
{
    protected static ?string $cluster = HargaAcuanProduk::class;

    protected static ?string $navigationLabel = 'Origami';

    protected static ?string $modelLabel = 'harga acuan origami';

    protected static ?string $pluralModelLabel = 'Harga Acuan Origami';

    protected static ?int $navigationSort = 1;

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
            fn (Builder $query) => $query->where('kategori', StokBarangGudang::KATEGORI_ORIGAMI)
        );
    }

    public static function form(Schema $schema): Schema
    {
        return HargaAcuanOrigamiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HargaAcuanOrigamisTable::configure($table);
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
            'index' => ListHargaAcuanOrigamis::route('/'),
            'create' => CreateHargaAcuanOrigami::route('/create'),
            'edit' => EditHargaAcuanOrigami::route('/{record}/edit'),
        ];
    }
}