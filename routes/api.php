<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['webhook.signature'])->group(function () {
    Route::post('/webhook/stok-masuk', [\App\Http\Controllers\WebhookController::class, 'stokMasuk']);
    Route::post('/webhook/bahan-baku-masuk', [\App\Http\Controllers\WebhookController::class, 'bahanBakuMasuk']);
    Route::post('/webhook/penjualan', [\App\Http\Controllers\WebhookController::class, 'penjualan']);
    Route::post('/webhook/alokasi-ai', [\App\Http\Controllers\WebhookController::class, 'alokasiAi']);
    Route::post('/webhook/update-etalase', [\App\Http\Controllers\WebhookController::class, 'updateEtalase']);
    Route::get('/stok/{sku}', [\App\Http\Controllers\WebhookController::class, 'stok']);
});
