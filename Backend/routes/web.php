<?php

use App\Http\Controllers\LatestApkController;
use App\Http\Controllers\PublicStorageController;
use App\Models\User;
use App\Services\AdminCenterContext;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::post('/admin/clear-center', function () {
    $user = auth()->user();
    abort_unless($user instanceof User && $user->isAdmin(), 403);
    app(AdminCenterContext::class)->forget();

    return redirect('/admin');
})->middleware('auth')->name('admin.clear-center');

Route::get('/storage/{path}', PublicStorageController::class)
    ->where('path', '.*')
    ->name('public-storage');

Route::get('/apk/paramfieldtrack-latest.apk', LatestApkController::class)
    ->name('apk.latest');
