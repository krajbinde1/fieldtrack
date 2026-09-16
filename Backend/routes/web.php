<?php

use App\Http\Controllers\LatestApkController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/apk/paramfieldtrack-latest.apk', LatestApkController::class)
    ->name('apk.latest');
