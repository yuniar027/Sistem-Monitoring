<?php

namespace App\Filament\Resources\ProdukMasterResource\Pages;

use App\Filament\Imports\ProdukMasterImporter;
use App\Filament\Resources\ProdukMasterResource;
use App\Services\HppService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\App;

class ListProdukMasters extends ListRecords
{
    protected static string $resource = ProdukMasterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('hitungHpp')
                ->label('Hitung Ulang HPP')
                ->icon('heroicon-o-calculator')
                ->color('success')
                ->action(function () {
                    $service = App::make(HppService::class);
                    $hasil = $service->updateHppSemuaProdukRakitan();

                    $jumlahBerhasil = count($hasil['berhasil']);
                    $jumlahDilewati = count($hasil['dilewati']);

                    Notification::make()
                        ->title('Perhitungan HPP selesai')
                        ->body("{$jumlahBerhasil} SKU berhasil diupdate. {$jumlahDilewati} SKU dilewati (resep atau data harga bahan baku belum lengkap).")
                        ->success()
                        ->send();
                }),
            ImportAction::make()
                ->importer(ProdukMasterImporter::class)
                ->label('Import Produk Master')
                ->chunkSize(250),
            CreateAction::make(),
        ];
    }
}
