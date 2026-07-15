<?php

namespace App\Filament\Widgets;

use App\Models\ProdukMaster;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class CekKonsistensiStokWidget extends TableWidget
{
    protected static ?string $heading = 'Breakdown & Sanity Check Stok';

    protected function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return ProdukMaster::query()
            ->select([
                'produk_master.id',
                'produk_master.sku',
                'produk_master.nama_produk',
                DB::raw('COALESCE(stok_mentah.kuantitas_tersedia, 0) AS stok_mentah'),
                DB::raw('COALESCE((
                    SELECT SUM(kuantitas_per_paket * jumlah_paket)
                    FROM stok_paket
                    WHERE stok_paket.sku = produk_master.sku
                        AND stok_paket.status = \'belum_distribusi\'
                ), 0) AS stok_paket_belum_distribusi'),
                DB::raw('COALESCE((
                    SELECT SUM(kuantitas_sisa)
                    FROM alokasi_etalase
                    WHERE alokasi_etalase.sku = produk_master.sku
                        AND alokasi_etalase.status = \'aktif\'
                ), 0) AS alokasi_etalase_belum_terjual'),
                DB::raw('CASE
                    WHEN COALESCE(stok_mentah.kuantitas_tersedia, 0) < 0 THEN \'Problem\'
                    WHEN EXISTS(
                        SELECT 1 FROM stok_paket
                        WHERE stok_paket.sku = produk_master.sku
                            AND stok_paket.jumlah_paket < 0
                    ) THEN \'Problem\'
                    WHEN EXISTS(
                        SELECT 1 FROM alokasi_etalase
                        WHERE alokasi_etalase.sku = produk_master.sku
                            AND alokasi_etalase.kuantitas_sisa < 0
                    ) THEN \'Problem\'
                    ELSE \'OK\'
                END AS sanity_check'),
            ])
            ->leftJoin('stok_mentah', 'stok_mentah.sku', '=', 'produk_master.sku');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')->sortable(),
                TextColumn::make('nama_produk')->label('Nama Produk')->sortable(),
                TextColumn::make('stok_mentah')->label('Stok Mentah')->sortable(),
                TextColumn::make('stok_paket_belum_distribusi')->label('Di Paket Belum Distribusi')->sortable(),
                TextColumn::make('alokasi_etalase_belum_terjual')->label('Teralokasi Belum Terjual')->sortable(),
                TextColumn::make('sanity_check')
                    ->label('Sanity Check')
                    ->color(fn (?string $state): string => $state === 'OK' ? 'success' : 'danger'),
            ])
            ->defaultSort('sku');
    }
}