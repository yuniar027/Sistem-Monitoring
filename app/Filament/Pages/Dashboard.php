<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ArusKasWidget;
use App\Filament\Widgets\DashboardSummaryWidget;
use App\Filament\Widgets\LabaRugiWidget;
use App\Filament\Widgets\RingkasanJurnalWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Dashboard';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-home';
    protected static ?string $title = 'Dashboard';

    public function getWidgets(): array
    {
        return [
            DashboardSummaryWidget::class,
            ArusKasWidget::class,
            LabaRugiWidget::class,
            RingkasanJurnalWidget::class,
        ];
    }
}
