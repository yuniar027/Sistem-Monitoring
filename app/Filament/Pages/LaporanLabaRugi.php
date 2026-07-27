<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\LabaRugiWidget;
use Filament\Pages\Page;

class LaporanLabaRugi extends Page
{
    protected static ?string $navigationLabel = 'Laporan Laba Rugi';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $title = 'Laporan Laba Rugi';

    // Sengaja tidak override $view - pakai template bawaan Filament yang
    // sudah otomatis merender widget, supaya tidak ada resiko salah CSS/blade lagi.

    protected function getHeaderWidgets(): array
    {
        return [
            LabaRugiWidget::class,
        ];
    }
}
