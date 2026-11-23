<?php

use App\Http\Controllers\EmployeeRouteLiveController;
use App\Http\Controllers\ExpenseReceiptController;
use App\Http\Controllers\LatestApkController;
use App\Http\Controllers\PublicStorageController;
use App\Models\User;
use App\Services\AdminCenterContext;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::post('/admin/clear-center', function () {
    $user = auth()->user();
    abort_unless(
        $user instanceof User && app(AdminCenterContext::class)->participatesInCenterSelection($user),
        403,
    );
    app(AdminCenterContext::class)->forget();

    return redirect('/admin/centers');
})->middleware('auth')->name('admin.clear-center');

Route::get('/storage/{path}', PublicStorageController::class)
    ->where('path', '.*')
    ->name('public-storage');

Route::get('/apk/paramfieldtrack-latest.apk', LatestApkController::class)
    ->name('apk.latest');

Route::middleware('auth')->group(function () {
    Route::get('/admin/employee-routes/tracking/overview', [EmployeeRouteLiveController::class, 'overview'])
        ->name('employee-routes.tracking.overview');
    Route::get('/admin/employee-routes/tracking/{attendance}', [EmployeeRouteLiveController::class, 'show'])
        ->whereNumber('attendance')
        ->name('employee-routes.tracking.show');
});

Route::middleware(FilamentAuthenticate::class)->group(function () {
    Route::get('/admin/expense-claims/{expenseClaim}/items/{item}/receipt', [ExpenseReceiptController::class, 'preview'])
        ->whereNumber('expenseClaim')
        ->whereNumber('item')
        ->name('admin.expense-receipts.preview');
    Route::get('/admin/expense-claims/{expenseClaim}/items/{item}/receipt/download', [ExpenseReceiptController::class, 'download'])
        ->whereNumber('expenseClaim')
        ->whereNumber('item')
        ->name('admin.expense-receipts.download');
});
