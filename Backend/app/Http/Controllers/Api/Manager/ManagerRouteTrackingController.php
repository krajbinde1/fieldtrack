<?php

namespace App\Http\Controllers\Api\Manager;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Services\EmployeeRouteAnalysisService;
use App\Services\OrganizationAccessService;
use App\Support\AttendanceCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Project Manager / Center Manager view-only route tracking.
 * Response shape matches Director route-tracking for shared mobile UI.
 */
class ManagerRouteTrackingController extends Controller
{
    public function __construct(
        private readonly OrganizationAccessService $access,
        private readonly EmployeeRouteAnalysisService $routeAnalysisService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:100'],
            'center_id' => ['nullable', 'integer'],
        ]);

        $date = $validated['date'] ?? AttendanceCalendar::today()->toDateString();
        $reportIds = $this->access->visibleEmployeeIds($request->user());
        $centerId = $this->access->requestedCenterId($request);

        if ($reportIds === []) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'date' => $date,
                    'total_employees' => 0,
                ],
            ]);
        }

        try {
            $employees = ($reportIds === null
                ? Employee::query()
                : Employee::query()->whereIn('id', $reportIds))
                ->with('user')
                ->where('status', true)
                ->when($centerId !== null, fn ($q) => $q->where('center_id', $centerId))
                ->when(filled($validated['search'] ?? null), function ($q) use ($validated): void {
                    $term = '%'.$validated['search'].'%';
                    $q->where(function ($inner) use ($term): void {
                        $inner->where('full_name', 'like', $term)
                            ->orWhere('employee_code', 'like', $term);
                    });
                })
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_code']);

            $employeeIds = $employees->pluck('id')->all();

            $attendances = $employeeIds === []
                ? collect()
                : Attendance::query()
                    ->whereIn('employee_id', $employeeIds)
                    ->whereDate('attendance_date', $date)
                    ->get()
                    ->keyBy('employee_id');

            $rows = $employees->map(function (Employee $employee) use ($attendances, $date): array {
                $role = (string) ($employee->user?->role ?? UserRole::Employee->value);
                $roleLabel = UserRole::tryFromMixed($role)->label();
                $attendance = $attendances->get($employee->id);

                if ($attendance === null) {
                    return [
                        'id' => null,
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->full_name,
                        'employee_code' => $employee->employee_code,
                        'role' => $role,
                        'role_label' => $roleLabel,
                        'attendance_date' => $date,
                        'attendance_status' => 'Not Punched In',
                        'display_status' => 'Not Punched In',
                        'punch_in_time' => null,
                        'punch_out_time' => null,
                        'working_hours' => null,
                        'total_working_minutes' => null,
                        'total_route_distance_km' => null,
                        'has_attendance' => false,
                        'has_route' => false,
                    ];
                }

                return $this->listItem($attendance, $employee, $role, $roleLabel);
            })->values();

            return response()->json([
                'data' => $rows,
                'meta' => [
                    'date' => $date,
                    'total_employees' => $rows->count(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Manager route-tracking index failed', [
                'date' => $date,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to load route tracking.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function show(Request $request, Attendance $attendance): JsonResponse
    {
        $this->ensureTeamSalesAttendance($request, $attendance);

        $attendance->load('employee.user');
        $analysis = $this->routeAnalysisService->analyze($attendance);
        $routePoints = $this->routeAnalysisService->formatRoutePointsForResponse($attendance);
        $hasRoute = count($routePoints) > 0
            || ($attendance->punch_in_latitude !== null && $attendance->punch_in_longitude !== null);

        $role = (string) ($attendance->employee?->user?->role ?? UserRole::Employee->value);

        return response()->json([
            'data' => [
                'employee' => [
                    'id' => $attendance->employee?->id,
                    'employee_code' => $attendance->employee?->employee_code,
                    'full_name' => $attendance->employee?->full_name,
                    'mobile' => $attendance->employee?->mobile,
                    'role' => $role,
                    'role_label' => UserRole::tryFromMixed($role)->label(),
                ],
                'attendance' => [
                    'id' => $attendance->id,
                    'attendance_date' => $attendance->attendance_date->toDateString(),
                    'attendance_status' => $attendance->attendance_status,
                    'display_status' => $this->displayStatus($attendance),
                    'approval_status' => $attendance->approval_status,
                    'working_hours' => $attendance->working_hours,
                    'total_working_minutes' => $attendance->total_working_minutes,
                    'total_route_distance_km' => $attendance->total_route_distance_km !== null
                        ? (float) $attendance->total_route_distance_km
                        : ($analysis['summary']['total_distance_km'] ?? null),
                    'punch_in' => [
                        'time' => $this->formatIstDateTime($attendance->punchInAt()),
                        'location' => $attendance->punch_in_location,
                        'latitude' => $attendance->punch_in_latitude !== null ? (float) $attendance->punch_in_latitude : null,
                        'longitude' => $attendance->punch_in_longitude !== null ? (float) $attendance->punch_in_longitude : null,
                    ],
                    'punch_out' => [
                        'time' => $this->formatIstDateTime($attendance->punchOutAt()),
                        'location' => $attendance->punch_out_location,
                        'latitude' => $attendance->punch_out_latitude !== null ? (float) $attendance->punch_out_latitude : null,
                        'longitude' => $attendance->punch_out_longitude !== null ? (float) $attendance->punch_out_longitude : null,
                    ],
                ],
                'summary' => $analysis['summary'],
                'has_route' => $hasRoute,
                'route_points' => $routePoints,
                'stops' => $analysis['stops'] ?? [],
                'timeline' => $analysis['timeline'] ?? [],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function listItem(
        Attendance $attendance,
        Employee $employee,
        string $role,
        string $roleLabel,
    ): array {
        return [
            'id' => $attendance->id,
            'employee_id' => $attendance->employee_id,
            'employee_name' => $employee->full_name,
            'employee_code' => $employee->employee_code,
            'role' => $role,
            'role_label' => $roleLabel,
            'attendance_date' => $attendance->attendance_date->toDateString(),
            'attendance_status' => $attendance->attendance_status,
            'display_status' => $this->displayStatus($attendance),
            'punch_in_time' => $this->formatIstDateTime($attendance->punchInAt()),
            'punch_out_time' => $this->formatIstDateTime($attendance->punchOutAt()),
            'working_hours' => $attendance->working_hours,
            'total_working_minutes' => $attendance->total_working_minutes,
            'total_route_distance_km' => $attendance->total_route_distance_km !== null
                ? (float) $attendance->total_route_distance_km
                : null,
            'has_attendance' => true,
            'has_route' => $attendance->total_route_distance_km !== null
                || ($attendance->punch_in_latitude !== null && $attendance->punch_in_longitude !== null),
        ];
    }

    private function ensureTeamSalesAttendance(Request $request, Attendance $attendance): void
    {
        $this->access->assertCanViewAttendance($request->user(), $attendance);
    }

    private function displayStatus(Attendance $attendance): string
    {
        if ($attendance->punchInAt() === null) {
            return 'Not Punched In';
        }

        if ($attendance->punchOutAt() === null) {
            return 'Active';
        }

        return 'Completed';
    }

    private function formatIstDateTime(?Carbon $value): ?string
    {
        return $value?->timezone(AttendanceCalendar::TIMEZONE)->toIso8601String();
    }
}
