<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImportFileDownloadController;
use App\Http\Controllers\StokRendahController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/imports/download/{filename}', ImportFileDownloadController::class)
    ->name('imports.download')
    ->middleware('signed');

Route::get('/api/stok-rendah', StokRendahController::class)
    ->name('api.stok-rendah');