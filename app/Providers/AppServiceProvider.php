<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Models\StokMasuk;
use App\Observers\StokMasukObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        // Morph map for `jurnal_umum.sumber_tipe` polymorphic relation
        Relation::morphMap([
            'stok_masuk' => \App\Models\StokMasuk::class,
            'transaksi_penjualan' => \App\Models\TransaksiPenjualan::class,
            'tugas_packing' => \App\Models\TugasPacking::class,
            'alokasi_etalase' => \App\Models\AlokasiEtalase::class,
            'stok_paket' => \App\Models\StokPaket::class,
            'bahan_baku_masuk' => \App\Models\BahanBakuMasuk::class,
        ], false);

        // Register observer to handle stok_masuk -> stok_mentah conversion
        StokMasuk::observe(StokMasukObserver::class);
    }
}