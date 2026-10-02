<?php

namespace App\Providers\Filament;

use App\Filament\Pages\AnalisisHargaPage;
use App\Filament\Pages\GudangBeranda;
use App\Filament\Pages\ImportSuratJalanGudang;
use App\Filament\Pages\InputStokHarianGabungan;
use App\Filament\Pages\LaporanKebutuhanStok;
use App\Filament\Pages\LaporanLabaRugiGudang;
use App\Filament\Pages\StokMati;
use App\Filament\Resources\BiayaOperasionals\BiayaOperasionalResource;
use App\Filament\Resources\HargaAcuanAwans\HargaAcuanAwanResource;
use App\Filament\Resources\HargaAcuanOrigamis\HargaAcuanOrigamiResource;
use App\Filament\Resources\PembelianGudangs\PembelianGudangResource;
use App\Filament\Resources\PenjualanGelondongans\PenjualanGelondonganResource;
use App\Filament\Resources\ProductionProcessTargets\ProductionProcessTargetResource;
use App\Filament\Resources\StokBarangGudangResource;
use App\Filament\Resources\StokVariasiGudangs\StokVariasiGudangResource;
use App\Filament\Resources\StokVariasiHarianResource;
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
use App\Filament\Pages\RingkasanStokPage;

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
                StokVariasiHarianResource::class,
                StokVariasiGudangResource::class,
                ProductionProcessTargetResource::class,
                HargaAcuanOrigamiResource::class,
                HargaAcuanAwanResource::class,
                PembelianGudangResource::class,
                BiayaOperasionalResource::class,
                PenjualanGelondonganResource::class,
            ])
            // Cluster "Harga Acuan Produk" (submenu Origami & Awan) cuma bisa
            // didaftarkan lewat discoverClusters -- tidak ada clusters([...]).
            ->discoverClusters(
                in: app_path('Filament/Clusters'),
                for: 'App\\Filament\\Clusters',
            )
            ->pages([
                GudangBeranda::class,
                InputStokHarianGabungan::class,
                ImportSuratJalanGudang::class,
                LaporanKebutuhanStok::class,
                LaporanLabaRugiGudang::class,
                StokMati::class,
                AnalisisHargaPage::class,
                RingkasanStokPage::class
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