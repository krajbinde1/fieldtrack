<?php

use App\Http\Controllers\Api\AdminEmployeeRouteController;
use App\Http\Controllers\Api\AppVersionController;
use App\Http\Controllers\Api\AdmissionLookupController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\Director\DirectorRouteTrackingController;
use App\Http\Controllers\Api\EmployeeAdmissionController;
use App\Http\Controllers\Api\EmployeeAdmissionTargetController;
use App\Http\Controllers\Api\EmployeeAuthController;
use App\Http\Controllers\Api\EmployeeLeaveController;
use App\Http\Controllers\Api\EmployeeRoutePointController;
use App\Http\Controllers\Api\Manager\ManagerAdmissionTargetController;
use App\Http\Controllers\Api\Manager\ManagerEmployeeController;
use App\Http\Controllers\Api\Manager\ManagerRouteTrackingController;
use App\Http\Controllers\Api\Manager\ManagerTeamAttendanceController;
use App\Http\Controllers\Api\SupervisorAdmissionController;
use App\Http\Controllers\Api\SupervisorLeaveController;
use Illuminate\Support\Facades\Route;

Route::get('app-version', AppVersionController::class);
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
        Route::get('employees', [ManagerEmployeeController::class, 'index']);
        Route::post('employees', [ManagerEmployeeController::class, 'store']);
        Route::get('admission-targets', [ManagerAdmissionTargetController::class, 'index']);
        Route::post('admission-targets', [ManagerAdmissionTargetController::class, 'store']);
        Route::get('admission-targets/{admissionTarget}', [ManagerAdmissionTargetController::class, 'show'])
            ->whereNumber('admissionTarget');
        Route::get('admissions', [SupervisorAdmissionController::class, 'index']);
        Route::get('admissions/summary', [SupervisorAdmissionController::class, 'summary']);
        Route::get('admissions/{admission}', [SupervisorAdmissionController::class, 'show'])
            ->whereNumber('admission');
        Route::post('admissions/{admission}/confirm', [SupervisorAdmissionController::class, 'confirm'])
            ->whereNumber('admission');
        Route::post('admissions/{admission}/revert', [SupervisorAdmissionController::class, 'revert'])
            ->whereNumber('admission');
        Route::post('admissions/{admission}/reject', [SupervisorAdmissionController::class, 'reject'])
            ->whereNumber('admission');
        Route::get('admissions/{admission}/documents/{document}', [SupervisorAdmissionController::class, 'downloadDocument'])
            ->whereNumber('admission')
            ->whereNumber('document');
        Route::get('leaves', [SupervisorLeaveController::class, 'index']);
        Route::get('leaves/{leave}', [SupervisorLeaveController::class, 'show'])
            ->whereNumber('leave');
        Route::post('leaves/{leave}/approve', [SupervisorLeaveController::class, 'approve'])
            ->whereNumber('leave');
        Route::post('leaves/{leave}/reject', [SupervisorLeaveController::class, 'reject'])
            ->whereNumber('leave');
        Route::get('leaves/{leave}/document', [SupervisorLeaveController::class, 'downloadDocument'])
            ->whereNumber('leave');
        Route::get('team-attendance', [ManagerTeamAttendanceController::class, 'index']);
        Route::get('team-attendance/employees/{employee}', [ManagerTeamAttendanceController::class, 'employeeHistory'])
            ->whereNumber('employee');
        Route::get('team-attendance/{attendance}', [ManagerTeamAttendanceController::class, 'show'])
            ->whereNumber('attendance');
        Route::get('route-tracking', [ManagerRouteTrackingController::class, 'index']);
        Route::get('route-tracking/{attendance}', [ManagerRouteTrackingController::class, 'show'])
            ->whereNumber('attendance');
    });

    Route::middleware('role:admin,director')->prefix('director')->group(function () {
        Route::get('route-tracking', [DirectorRouteTrackingController::class, 'index']);
        Route::get('route-tracking/{attendance}', [DirectorRouteTrackingController::class, 'show']);
        Route::get('team-attendance', [ManagerTeamAttendanceController::class, 'index']);
        Route::get('team-attendance/employees/{employee}', [ManagerTeamAttendanceController::class, 'employeeHistory']);
        Route::get('team-attendance/{attendance}', [ManagerTeamAttendanceController::class, 'show']);
        Route::get('admissions', [SupervisorAdmissionController::class, 'index']);
        Route::get('admissions/summary', [SupervisorAdmissionController::class, 'summary']);
        Route::get('admissions/{admission}', [SupervisorAdmissionController::class, 'show']);
        Route::get('admissions/{admission}/documents/{document}', [SupervisorAdmissionController::class, 'downloadDocument']);
        Route::get('leaves', [SupervisorLeaveController::class, 'index']);
        Route::get('leaves/{leave}', [SupervisorLeaveController::class, 'show']);
        Route::get('leaves/{leave}/document', [SupervisorLeaveController::class, 'downloadDocument']);
    });
});

Route::middleware(['auth:sanctum', 'role:employee'])->prefix('attendance')->group(function () {
    Route::post('punch-in', [AttendanceController::class, 'punchIn']);
    Route::post('punch-out', [AttendanceController::class, 'punchOut']);
    Route::get('today', [AttendanceController::class, 'today']);
    Route::get('history', [AttendanceController::class, 'history']);
    Route::get('monthly-summary', [AttendanceController::class, 'monthlySummary']);
});

Route::middleware(['auth:sanctum', 'role:employee'])->prefix('admissions')->group(function () {
    Route::get('schemes', [AdmissionLookupController::class, 'schemes']);
    Route::get('districts', [AdmissionLookupController::class, 'districts']);
    Route::get('districts/{district}/talukas', [AdmissionLookupController::class, 'talukas']);
    Route::get('drafts', [EmployeeAdmissionController::class, 'drafts']);
    Route::post('drafts', [EmployeeAdmissionController::class, 'storeDraft']);
    Route::patch('drafts/{admission}', [EmployeeAdmissionController::class, 'updateDraft']);
    Route::delete('drafts/{admission}', [EmployeeAdmissionController::class, 'destroyDraft']);
    Route::get('submitted', [EmployeeAdmissionController::class, 'submitted']);
    Route::get('summary', [EmployeeAdmissionController::class, 'summary']);
    Route::get('targets/summary', [EmployeeAdmissionTargetController::class, 'summary']);
    Route::get('targets', [EmployeeAdmissionTargetController::class, 'index']);
    Route::post('{admission}/submit', [EmployeeAdmissionController::class, 'submit']);
    Route::post('{admission}/documents', [EmployeeAdmissionController::class, 'uploadDocument']);
    Route::delete('{admission}/documents/{document}', [EmployeeAdmissionController::class, 'removeDocument']);
    Route::get('{admission}/documents/{document}', [EmployeeAdmissionController::class, 'downloadDocument']);
    Route::get('{admission}', [EmployeeAdmissionController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'role:employee'])->prefix('leaves')->group(function () {
    Route::get('/', [EmployeeLeaveController::class, 'index']);
    Route::post('/', [EmployeeLeaveController::class, 'store']);
    Route::get('{leave}/document', [EmployeeLeaveController::class, 'downloadDocument']);
    Route::post('{leave}/document', [EmployeeLeaveController::class, 'uploadDocument']);
    Route::delete('{leave}/document', [EmployeeLeaveController::class, 'removeDocument']);
    Route::get('{leave}', [EmployeeLeaveController::class, 'show']);
    Route::patch('{leave}', [EmployeeLeaveController::class, 'update']);
    Route::delete('{leave}', [EmployeeLeaveController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'role:admin,director,project_head,center_manager'])->prefix('admin')->group(function () {
    Route::get('employee-routes/{attendance}', [AdminEmployeeRouteController::class, 'show']);
});
