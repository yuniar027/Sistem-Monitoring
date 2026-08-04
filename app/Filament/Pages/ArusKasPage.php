<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ArusKasWidget;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class ArusKasPage extends Page
{
    use HasFiltersAction;

    protected static ?string $navigationLabel = 'Laporan Arus Kas';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';
    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';
    protected static ?string $title = 'Laporan Arus Kas';

    protected function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->label('Filter Tanggal')
                ->form([
                    DatePicker::make('tanggal_awal')
                        ->label('Dari Tanggal')
                        ->default(Carbon::now()->startOfMonth())
                        ->native(false),
                    DatePicker::make('tanggal_akhir')
                        ->label('Sampai Tanggal')
                        ->default(Carbon::now()->endOfMonth())
                        ->native(false),
                ]),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ArusKasWidget::class,
        ];
    }
}