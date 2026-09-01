<?php

namespace App\Providers\Filament;

use App\Filament\Pages\LaporanKebutuhanStok;
use App\Filament\Resources\StokBarangGudangResource;
use App\Filament\Resources\StokHarianGudangResource;
use App\Filament\Resources\StokVariasiHarianResource;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class GudangPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('gudang')
            ->path('gudang')
            ->login()
            ->authGuard('gudang')
            ->brandName('Umma IMS - Gudang')
            // resource & page didaftarkan MANUAL (bukan discoverResources),
            // supaya panel ini cuma nampilin modul Monitoring Stok Ringkas
            // dan nggak ke-mix sama resource sistem besar di /admin.
            ->resources([
                StokBarangGudangResource::class,
                StokHarianGudangResource::class,
                StokVariasiHarianResource::class,
            ])
            ->pages([
                LaporanKebutuhanStok::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                AuthenticateSession::class,
            ]);
    }
}
