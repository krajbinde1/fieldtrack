<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaveStatus;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Services\DirectorWorkforceService;
use App\Services\OrganizationAccessService;
use App\Support\AttendanceCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, OrganizationAccessService $access, DirectorWorkforceService $workforce): JsonResponse
    {
        $user = $request->user();
        $centerId = $access->requestedCenterId($request);
        $today = AttendanceCalendar::today()->toDateString();
        $employeeQuery = $access->employeeQuery($user)->where('status', true);
        if ($centerId !== null) {
            $employeeQuery->where('center_id', $centerId);
        }
        $employeeIds = (clone $employeeQuery)->pluck('id');
        $directorOrgDashboard = $user->isAdminOrDirector() && $centerId === null;
        $workforceSummary = $directorOrgDashboard
            ? $workforce->summarize($user)
            : null;

        $punchedIn = $workforceSummary['punched_in_today'] ?? Attendance::query()
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

        $admissionCounts = $access->admissionStatusCounts($user, $centerId);
        $fieldActivityQuery = $access->fieldActivityQuery($user);
        if ($centerId !== null) {
            $fieldActivityQuery->where('center_id', $centerId);
        }
        $leaveQuery = $directorOrgDashboard
            ? $access->projectHeadLeaveQuery($user)->where('status', LeaveStatus::Pending->value)
            : $access->leaveQuery($user)->where('status', LeaveStatus::Pending->value);
        $targetQuery = $access->admissionTargetQuery($user)->whereNull('parent_id');
        $centerQuery = $access->centerQuery($user);
        if ($centerId !== null) {
            $leaveQuery->where('center_id', $centerId);
            $targetQuery->where('center_id', $centerId);
            $centerQuery->where('id', $centerId);
        }

        $centerPayload = null;
        if ($centerId !== null) {
            $center = $access->assertCanViewCenter($user, $centerId)
                ->loadMissing(['scheme:id,name', 'centerManagers:id,name']);
            $managerName = $center->centerManagers
                ->pluck('name')
                ->filter(fn ($name) => filled($name) && ! str_contains((string) $name, '@'))
                ->values()
                ->join(', ');
            $centerPayload = [
                'id' => $center->id,
                'name' => $center->name,
                'scheme_name' => $center->scheme?->name,
                'project_name' => $center->scheme?->name,
                'center_manager_name' => $managerName !== '' ? $managerName : null,
                'is_active' => (bool) $center->is_active,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $user->role,
                'role_label' => $user->roleEnum()->label(),
                'schemes' => $access->schemeQuery($user)->count(),
                'projects' => $access->schemeQuery($user)->count(),
                'centers' => $centerQuery->count(),
                'active_centers' => (clone $centerQuery)->where('is_active', true)->count(),
                'employees' => $workforceSummary['total'] ?? $employeeIds->count(),
                'today' => $today,
                'punched_in_today' => $punchedIn,
                'punched_out_today' => $workforceSummary['punched_out_today'] ?? $punchedOut,
                'active_routes' => $activeRoutes,
                'not_punched_in_today' => $workforceSummary['not_punched_in_today']
                    ?? max(0, $employeeIds->count() - $punchedIn),
                'admissions' => $admissionCounts['submitted'],
                'admission_counts' => $admissionCounts,
                'confirmed_admissions' => $admissionCounts['confirmed'],
                'pending_leaves' => $leaveQuery->count(),
                'field_activities_today' => (clone $fieldActivityQuery)
                    ->whereDate('activity_at', $today)
                    ->count(),
                'pending_project_head_leaves' => $directorOrgDashboard
                    ? $leaveQuery->count()
                    : $access->projectHeadLeaveQuery($user)->where('status', LeaveStatus::Pending->value)->count(),
                'admission_targets' => $targetQuery->count(),
                'center' => $centerPayload,
            ],
        ]);
    }
}
