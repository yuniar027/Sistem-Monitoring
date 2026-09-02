<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StokRendahRingkasController;

Route::middleware(['webhook.signature'])->group(function () {
    Route::post('/webhook/stok-masuk', [\App\Http\Controllers\WebhookController::class, 'stokMasuk']);
    Route::post('/webhook/bahan-baku-masuk', [\App\Http\Controllers\WebhookController::class, 'bahanBakuMasuk']);
    Route::post('/webhook/penjualan', [\App\Http\Controllers\WebhookController::class, 'penjualan']);
    Route::post('/webhook/alokasi-ai', [\App\Http\Controllers\WebhookController::class, 'alokasiAi']);
    Route::post('/webhook/update-etalase', [\App\Http\Controllers\WebhookController::class, 'updateEtalase']);
    Route::get('/stok/{sku}', [\App\Http\Controllers\WebhookController::class, 'stok']);
    Route::post('/webhook/petakan-bahan-baku', [\App\Http\Controllers\WebhookController::class, 'petakanBahanBaku']);
    Route::get('/webhook/stok-rendah-ringkas', [StokRendahRingkasController::class, 'index'])->middleware('webhook.signature');
});
