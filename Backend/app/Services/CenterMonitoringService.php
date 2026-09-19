<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Center;
use App\Models\FieldActivity;
use App\Models\User;
use App\Support\AttendanceCalendar;
use Illuminate\Support\Collection;

class CenterMonitoringService
{
    public function __construct(private readonly OrganizationAccessService $access) {}

    /**
     * @param  Collection<int, Center>  $centers
     * @return list<array<string, mixed>>
     */
    public function payloads(User $user, Collection $centers): array
    {
        $centerIds = $centers->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($centerIds === []) {
            return [];
        }

        $today = AttendanceCalendar::today()->toDateString();
        $employees = $this->access->employeeQuery($user)
            ->where('status', true)
            ->whereIn('center_id', $centerIds)
            ->get(['id', 'center_id']);

        $employeeIdsByCenter = $employees->groupBy('center_id');
        $allEmployeeIds = $employees->pluck('id')->all();

        $punchedInByEmployee = $allEmployeeIds === []
            ? collect()
            : Attendance::query()
                ->whereIn('employee_id', $allEmployeeIds)
                ->whereDate('attendance_date', $today)
                ->whereNotNull('punch_in_time')
                ->get(['employee_id', 'punch_out_time'])
                ->keyBy('employee_id');

        $fieldActivitiesToday = FieldActivity::query()
            ->whereIn('center_id', $centerIds)
            ->whereDate('activity_at', $today)
            ->get(['center_id'])
            ->countBy('center_id');

        return $centers->map(function (Center $center) use ($employeeIdsByCenter, $punchedInByEmployee, $fieldActivitiesToday): array {
            $centerEmployees = $employeeIdsByCenter->get($center->id, collect());
            $employeeIds = $centerEmployees->pluck('id');
            $punchedIn = 0;
            $activeRoutes = 0;
            foreach ($employeeIds as $employeeId) {
                $attendance = $punchedInByEmployee->get($employeeId);
                if ($attendance === null) {
                    continue;
                }
                $punchedIn++;
                if (blank($attendance->punch_out_time)) {
                    $activeRoutes++;
                }
            }

            $managerName = $center->centerManagers
                ->pluck('name')
                ->filter(fn ($name) => filled($name) && ! str_contains((string) $name, '@'))
                ->values()
                ->join(', ');

            return [
                'id' => $center->id,
                'name' => $center->name,
                'code' => $center->code,
                'scheme_name' => $center->scheme?->name,
                'project_name' => $center->scheme?->name,
                'center_manager_name' => $managerName !== '' ? $managerName : null,
                'employees' => $employeeIds->count(),
                'punched_in_today' => $punchedIn,
                'active_routes' => $activeRoutes,
                'field_activities_today' => (int) ($fieldActivitiesToday[$center->id] ?? 0),
                'is_active' => (bool) $center->is_active,
            ];
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function emptyPayload(Center $center): array
    {
        return [
            'id' => $center->id,
            'name' => $center->name,
            'code' => $center->code,
            'scheme_name' => $center->scheme?->name,
            'project_name' => $center->scheme?->name,
            'center_manager_name' => null,
            'employees' => 0,
            'punched_in_today' => 0,
            'active_routes' => 0,
            'field_activities_today' => 0,
            'is_active' => (bool) $center->is_active,
        ];
    }
}
