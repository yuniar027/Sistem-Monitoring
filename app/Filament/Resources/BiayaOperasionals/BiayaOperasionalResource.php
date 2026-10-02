<?php

namespace App\Filament\Resources\BiayaOperasionals;

use App\Filament\Resources\BiayaOperasionals\Pages\CreateBiayaOperasional;
use App\Filament\Resources\BiayaOperasionals\Pages\EditBiayaOperasional;
use App\Filament\Resources\BiayaOperasionals\Pages\ListBiayaOperasionals;
use App\Filament\Resources\BiayaOperasionals\Pages\ViewBiayaOperasional;
use App\Filament\Resources\BiayaOperasionals\Schemas\BiayaOperasionalForm;
use App\Filament\Resources\BiayaOperasionals\Schemas\BiayaOperasionalInfolist;
use App\Filament\Resources\BiayaOperasionals\Tables\BiayaOperasionalsTable;
use App\Models\BiayaOperasional;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BiayaOperasionalResource extends Resource
{
    protected static ?string $model = BiayaOperasional::class;

    protected static ?string $modelLabel = 'Biaya Operasional';

    protected static ?string $pluralModelLabel = 'Biaya Operasional';

    protected static ?string $navigationLabel = 'Biaya Operasional';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    /**
     * Panel /admin memakai discoverResources, jadi resource ini otomatis
     * ikut terdeteksi di sana. Di /admin sudah ada halaman Biaya Operasional
     * sendiri, jadi resource ini dibatasi hanya untuk panel /gudang.
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
        return BiayaOperasionalForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BiayaOperasionalInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BiayaOperasionalsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBiayaOperasionals::route('/'),
            'create' => CreateBiayaOperasional::route('/create'),
            'view' => ViewBiayaOperasional::route('/{record}'),
            'edit' => EditBiayaOperasional::route('/{record}/edit'),
        ];
    }
}
