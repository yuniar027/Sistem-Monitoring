<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\RingkasanHargaWidget;
use App\Filament\Widgets\TrenHargaWidget;
use App\Models\StokBarangGudang;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class AnalisisHargaPage extends Page
{
    use HasFiltersAction;

    protected static ?string $navigationLabel = 'Analisis Harga';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $title = 'Analisis Kenaikan & Penurunan Harga';

    public static function canAccess(): bool
    {
        return Auth::guard('gudang')->user()?->bisaAksesKeuangan() ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->label('Filter')
                ->form([
                    Select::make('kategori')
                        ->label('Kategori')
                        ->options([
                            StokBarangGudang::KATEGORI_ORIGAMI => 'Origami',
                            StokBarangGudang::KATEGORI_AWAN => 'Awan',
                        ])
                        ->placeholder('Semua kategori')
                        ->native(false),

                    DatePicker::make('tanggal_awal')
                        ->label('Dari Tanggal')
                        ->default(
                            Carbon::now()->subMonths(6)->startOfMonth()
                        )
                        ->native(false),

                    DatePicker::make('tanggal_akhir')
                        ->label('Sampai Tanggal')
                        ->default(Carbon::now())
                        ->native(false),
                ]),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            RingkasanHargaWidget::class,
            TrenHargaWidget::class,
        ];
    }
}