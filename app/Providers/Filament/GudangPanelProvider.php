<?php

namespace App\Providers\Filament;

use App\Filament\Pages\GudangBeranda;
use App\Filament\Pages\InputStokHarianGabungan;
use App\Filament\Pages\LaporanKebutuhanStok;
use App\Filament\Pages\StokMati;
use App\Filament\Resources\StokBarangGudangResource;
use App\Filament\Resources\StokVariasiHarianResource;
use App\Filament\Resources\StokVariasiGudangs\StokVariasiGudangResource;
use App\Filament\Resources\ProductionProcessTargets\ProductionProcessTargetResource;
use Filament\Http\Middleware\Authenticate;
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
use App\Filament\Resources\HargaAcuanOrigamis\HargaAcuanOrigamiResource;
use App\Filament\Resources\PembelianGudangs\PembelianGudangResource;

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
            ->resources([
                StokBarangGudangResource::class,
                StokVariasiHarianResource::class,
                StokVariasiGudangResource::class,
                ProductionProcessTargetResource::class,
                HargaAcuanOrigamiResource::class,
                PembelianGudangResource::class,
            ])
            ->pages([
                GudangBeranda::class,
                InputStokHarianGabungan::class,
                LaporanKebutuhanStok::class,
                StokMati::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}