<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImportFileDownloadController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/imports/download/{filename}', ImportFileDownloadController::class)
    ->name('imports.download')
    ->middleware('signed');