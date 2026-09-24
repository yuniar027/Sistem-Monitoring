<?php

namespace App\Filament\Resources\PembelianGudangs;

use App\Filament\Resources\PembelianGudangs\Pages\CreatePembelianGudang;
use App\Filament\Resources\PembelianGudangs\Pages\EditPembelianGudang;
use App\Filament\Resources\PembelianGudangs\Pages\ListPembelianGudangs;
use App\Filament\Resources\PembelianGudangs\Schemas\PembelianGudangForm;
use App\Filament\Resources\PembelianGudangs\Tables\PembelianGudangsTable;
use App\Models\PembelianGudang;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PembelianGudangResource extends Resource
{
    protected static ?string $model = PembelianGudang::class;

    protected static ?string $navigationLabel = 'Pembelian / Invoice';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function canAccess(): bool 
    {
        return Auth::guard('gudang')->user()?->bisaAksesKeuangan() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return PembelianGudangForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PembelianGudangsTable::configure($table);
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
            'index' => ListPembelianGudangs::route('/'),
            'create' => CreatePembelianGudang::route('/create'),
            'view' => Pages\ViewPembelianGudang::route('/{record}'),
            'edit' => EditPembelianGudang::route('/{record}/edit'),
        ];
    }
}
