<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\LabaRugiWidget;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class LaporanLabaRugi extends Page
{
    use HasFiltersAction;

    protected static ?string $navigationLabel = 'Laporan Laba Rugi';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static string|\UnitEnum|null $navigationGroup = 'Keuangan';
    protected static ?string $title = 'Laporan Laba Rugi';

    // Sengaja tidak override $view - pakai template bawaan Filament yang
    // sudah otomatis merender widget, supaya tidak ada resiko salah CSS/blade lagi.

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
            LabaRugiWidget::class,
        ];
    }
}