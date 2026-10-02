<?php

namespace App\Filament\Resources\PenjualanGelondongans;

use App\Filament\Resources\PenjualanGelondongans\Pages\CreatePenjualanGelondongan;
use App\Filament\Resources\PenjualanGelondongans\Pages\EditPenjualanGelondongan;
use App\Filament\Resources\PenjualanGelondongans\Pages\ListPenjualanGelondongans;
use App\Filament\Resources\PenjualanGelondongans\Pages\ViewPenjualanGelondongan;
use App\Filament\Resources\PenjualanGelondongans\Schemas\PenjualanGelondonganForm;
use App\Filament\Resources\PenjualanGelondongans\Schemas\PenjualanGelondonganInfolist;
use App\Filament\Resources\PenjualanGelondongans\Tables\PenjualanGelondongansTable;
use App\Models\PenjualanGelondongan;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PenjualanGelondonganResource extends Resource
{
    protected static ?string $model = PenjualanGelondongan::class;

    protected static ?string $modelLabel = 'Penjualan Gelondongan';

    protected static ?string $pluralModelLabel = 'Penjualan Gelondongan';

    protected static ?string $navigationLabel = 'Penjualan (Gelondongan)';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    /**
     * Panel /admin memakai discoverResources, jadi resource ini dibatasi
     * hanya untuk panel /gudang supaya tidak muncul di /admin.
     */
    public static function canAccess(): bool
    {
        if (Filament::getCurrentPanel()?->getId() !== 'gudang') {
            return false;
        }

        return auth('gudang')->user()?->canAksesKeuangan() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return PenjualanGelondonganForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PenjualanGelondonganInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PenjualanGelondongansTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPenjualanGelondongans::route('/'),
            'create' => CreatePenjualanGelondongan::route('/create'),
            'view' => ViewPenjualanGelondongan::route('/{record}'),
            'edit' => EditPenjualanGelondongan::route('/{record}/edit'),
        ];
    }
}
