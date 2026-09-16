<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Services\OrganizationAccessService;
use App\Support\AttendanceCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, OrganizationAccessService $access): JsonResponse
    {
        $user = $request->user();
        $today = AttendanceCalendar::today()->toDateString();
        $employeeQuery = $access->employeeQuery($user)->where('status', true);
        $employeeIds = (clone $employeeQuery)->pluck('id');

        $punchedIn = Attendance::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('attendance_date', $today)
            ->whereNotNull('punch_in_time')
            ->count();

        $activeRoutes = Attendance::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('attendance_date', $today)
            ->whereNotNull('punch_in_time')
            ->whereNull('punch_out_time')
            ->count();

        $punchedOut = Attendance::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('attendance_date', $today)
            ->whereNotNull('punch_out_time')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $user->role,
                'role_label' => $user->roleEnum()->label(),
                'schemes' => $access->schemeQuery($user)->count(),
                'projects' => $access->schemeQuery($user)->count(),
                'centers' => $access->centerQuery($user)->count(),
                'employees' => $employeeIds->count(),
                'today' => $today,
                'punched_in_today' => $punchedIn,
                'punched_out_today' => $punchedOut,
                'active_routes' => $activeRoutes,
                'not_punched_in_today' => max(0, $employeeIds->count() - $punchedIn),
            ],
        ]);
    }
}
