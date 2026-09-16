<?php

use App\Http\Controllers\Api\AdminEmployeeRouteController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\Director\DirectorRouteTrackingController;
use App\Http\Controllers\Api\EmployeeAuthController;
use App\Http\Controllers\Api\EmployeeRoutePointController;
use App\Http\Controllers\Api\Manager\ManagerRouteTrackingController;
use App\Http\Controllers\Api\Manager\ManagerTeamAttendanceController;
use Illuminate\Support\Facades\Route;

Route::post('login', [EmployeeAuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'mobile.session'])->group(function () {
    Route::post('logout', [EmployeeAuthController::class, 'logout']);
    Route::get('me', [EmployeeAuthController::class, 'me']);
    Route::post('change-password', [EmployeeAuthController::class, 'changePassword']);
    Route::post('profile-photo', [EmployeeAuthController::class, 'updateProfilePhoto']);
    Route::get('dashboard', DashboardController::class);

    Route::middleware('role:employee')->group(function () {
        if (! app()->environment('production')) {
            Route::post('employee/attendance/reset-today', [AttendanceController::class, 'resetToday']);
        }
        Route::post('employee/route-points/batch', [EmployeeRoutePointController::class, 'storeBatch']);
    });

    Route::middleware('role:project_head,center_manager')->prefix('manager')->group(function () {
        Route::get('team-attendance', [ManagerTeamAttendanceController::class, 'index']);
        Route::get('team-attendance/employees/{employee}', [ManagerTeamAttendanceController::class, 'employeeHistory']);
        Route::get('team-attendance/{attendance}', [ManagerTeamAttendanceController::class, 'show']);
        Route::get('route-tracking', [ManagerRouteTrackingController::class, 'index']);
        Route::get('route-tracking/{attendance}', [ManagerRouteTrackingController::class, 'show']);
    });

    Route::middleware('role:director')->prefix('director')->group(function () {
        Route::get('route-tracking', [DirectorRouteTrackingController::class, 'index']);
        Route::get('route-tracking/{attendance}', [DirectorRouteTrackingController::class, 'show']);
        Route::get('team-attendance', [ManagerTeamAttendanceController::class, 'index']);
        Route::get('team-attendance/employees/{employee}', [ManagerTeamAttendanceController::class, 'employeeHistory']);
        Route::get('team-attendance/{attendance}', [ManagerTeamAttendanceController::class, 'show']);
    });
});

Route::middleware(['auth:sanctum', 'role:employee'])->prefix('attendance')->group(function () {
    Route::post('punch-in', [AttendanceController::class, 'punchIn']);
    Route::post('punch-out', [AttendanceController::class, 'punchOut']);
    Route::get('today', [AttendanceController::class, 'today']);
    Route::get('history', [AttendanceController::class, 'history']);
    Route::get('monthly-summary', [AttendanceController::class, 'monthlySummary']);
});

Route::middleware(['auth:sanctum', 'role:director,project_head,center_manager'])->prefix('admin')->group(function () {
    Route::get('employee-routes/{attendance}', [AdminEmployeeRouteController::class, 'show']);
});
