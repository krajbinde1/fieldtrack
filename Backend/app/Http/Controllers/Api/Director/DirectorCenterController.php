<?php

namespace App\Http\Controllers\Api\Director;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Center;
use App\Services\OrganizationAccessService;
use App\Support\AttendanceCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DirectorCenterController extends Controller
{
    public function __construct(private readonly OrganizationAccessService $access) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $centers = $this->access->centerQuery($user)
            ->with(['scheme:id,name', 'centerManagers:id,name'])
            ->where('is_active', true)
            ->when(filled($validated['search'] ?? null), function ($query) use ($validated): void {
                $term = '%'.$validated['search'].'%';
                $query->where('name', 'like', $term);
            })
            ->orderBy('name')
            ->get();

        $payload = $this->centerPayloads($user, $centers);

        return response()->json([
            'success' => true,
            'data' => $payload,
            'meta' => [
                'total' => count($payload),
            ],
        ]);
    }

    public function show(Request $request, Center $center): JsonResponse
    {
        $user = $request->user();
        $this->access->assertCanViewCenter($user, (int) $center->id);
        $center->loadMissing(['scheme:id,name', 'centerManagers:id,name']);

        $payloads = $this->centerPayloads($user, collect([$center]));

        return response()->json([
            'success' => true,
            'data' => $payloads[0] ?? $this->emptyPayload($center),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Center>  $centers
     * @return list<array<string, mixed>>
     */
    private function centerPayloads($user, $centers): array
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

        return $centers->map(function (Center $center) use ($employeeIdsByCenter, $punchedInByEmployee): array {
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
                'is_active' => (bool) $center->is_active,
            ];
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(Center $center): array
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
            'is_active' => (bool) $center->is_active,
        ];
    }
}
