<?php

use App\Http\Controllers\LatestApkController;
use App\Http\Controllers\PublicStorageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/storage/{path}', PublicStorageController::class)
    ->where('path', '.*')
    ->name('public-storage');

Route::get('/apk/paramfieldtrack-latest.apk', LatestApkController::class)
    ->name('apk.latest');
