<?php

namespace App\Services;

use App\Enums\CenterStaffRole;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Center;
use App\Models\Employee;
use App\Models\Scheme;
use App\Models\User;
use App\Support\AttendanceCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class DirectorWorkforceService
{
    /**
     * @return list<string>
     */
    public const ROLE_PRIORITY = [
        UserRole::ProjectHead->value,
        UserRole::CenterManager->value,
        CenterStaffRole::Mis->value,
        CenterStaffRole::Trainer->value,
        CenterStaffRole::Mobilizer->value,
    ];

    public function __construct(private readonly OrganizationAccessService $access) {}

    /**
     * @return array{total: int, punched_in_today: int, punched_out_today: int, not_punched_in_today: int}
     */
    public function summarize(User $viewer, ?int $centerId = null): array
    {
        $people = $this->collect($viewer, $centerId);

        $punchedIn = $people->filter(
            fn (array $row): bool => in_array($row['attendance_status'], ['punched_in', 'punched_out'], true),
        )->count();
        $punchedOut = $people->where('attendance_status', 'punched_out')->count();

        return [
            'total' => $people->count(),
            'punched_in_today' => $punchedIn,
            'punched_out_today' => $punchedOut,
            'not_punched_in_today' => max(0, $people->count() - $punchedIn),
        ];
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function index(Request $request): array
    {
        $viewer = $request->user();
        $centerId = $this->access->requestedCenterId($request);
        $schemeId = $request->filled('scheme_id') ? $request->integer('scheme_id') : ($request->filled('project_id') ? $request->integer('project_id') : null);
        $role = filled($request->input('role')) ? (string) $request->input('role') : null;
        $attendanceStatus = filled($request->input('attendance_status')) ? (string) $request->input('attendance_status') : null;
        $search = filled($request->input('search')) ? trim((string) $request->input('search')) : null;

        $people = $this->collect($viewer, $centerId, $schemeId)
            ->when($role !== null && $role !== '', fn (Collection $rows) => $rows->filter(
                function (array $row) use ($role): bool {
                    if ($role === 'other') {
                        return ! in_array($row['role'], self::ROLE_PRIORITY, true);
                    }

                    return $row['role'] === $role;
                },
            )->values())
            ->when($attendanceStatus !== null && $attendanceStatus !== '', fn (Collection $rows) => $rows->where('attendance_status', $attendanceStatus)->values())
            ->when($search !== null && $search !== '', function (Collection $rows) use ($search): Collection {
                $needle = mb_strtolower($search);

                return $rows->filter(function (array $row) use ($needle): bool {
                    $haystack = mb_strtolower(implode(' ', array_filter([
                        $row['full_name'] ?? '',
                        $row['login_id'] ?? '',
                        $row['employee_code'] ?? '',
                    ])));

                    return str_contains($haystack, $needle);
                })->values();
            });

        $sorted = $people->sort(function (array $left, array $right): int {
            $priority = $this->roleRank($left['role']) <=> $this->roleRank($right['role']);
            if ($priority !== 0) {
                return $priority;
            }

            return strcasecmp((string) $left['full_name'], (string) $right['full_name']);
        })->values();

        return [
            'data' => $sorted->all(),
            'meta' => [
                'total' => $sorted->count(),
                'punched_in_today' => $sorted->filter(
                    fn (array $row): bool => in_array($row['attendance_status'], ['punched_in', 'punched_out'], true),
                )->count(),
                'roles' => $this->roleFilterOptions(),
                'schemes' => $this->access->schemeQuery($viewer)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Scheme $scheme): array => [
                        'id' => $scheme->id,
                        'name' => $scheme->name,
                    ])
                    ->values()
                    ->all(),
                'centers' => $this->access->centerQuery($viewer)
                    ->with('scheme:id,name')
                    ->orderBy('name')
                    ->get(['id', 'name', 'scheme_id'])
                    ->map(fn (Center $center): array => [
                        'id' => $center->id,
                        'name' => $center->name,
                        'scheme_id' => $center->scheme_id,
                        'scheme_name' => $center->scheme?->name,
                    ])
                    ->values()
                    ->all(),
                'attendance_statuses' => [
                    ['value' => 'punched_in', 'label' => 'Punched In'],
                    ['value' => 'punched_out', 'label' => 'Punched Out'],
                    ['value' => 'not_punched_in', 'label' => 'Not Punched In'],
                ],
                'can_create' => false,
            ],
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function collect(User $viewer, ?int $centerId = null, ?int $schemeId = null): Collection
    {
        $today = AttendanceCalendar::today()->toDateString();
        $orgUsers = $this->orgUsers($viewer, $centerId, $schemeId);
        $linkedEmployeeIds = $orgUsers
            ->pluck('employee_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $employees = $this->fieldEmployees($viewer, $centerId, $schemeId, $linkedEmployeeIds);
        $employeeIds = $orgUsers->pluck('employee_id')->filter()
            ->merge($employees->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $attendances = $employeeIds === []
            ? collect()
            : Attendance::query()
                ->whereIn('employee_id', $employeeIds)
                ->whereDate('attendance_date', $today)
                ->whereNotNull('punch_in_time')
                ->get(['employee_id', 'punch_in_time', 'punch_out_time'])
                ->keyBy('employee_id');

        $rows = collect();

        foreach ($orgUsers as $user) {
            $rows->push($this->orgUserRow($user, $attendances->get((int) $user->employee_id)));
        }

        foreach ($employees as $employee) {
            $rows->push($this->employeeRow($employee, $attendances->get((int) $employee->id)));
        }

        return $rows->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function orgUsers(User $viewer, ?int $centerId, ?int $schemeId): Collection
    {
        $visibleCenterIds = $this->access->visibleCenterIds($viewer);
        $roles = $viewer->isProjectHead()
            ? [UserRole::CenterManager->value]
            : [UserRole::ProjectHead->value, UserRole::CenterManager->value];

        return User::query()
            ->where('is_active', true)
            ->whereIn('role', $roles)
            ->with([
                'employee:id,employee_code,center_id',
                'headedCenters' => fn ($query) => $query->with('scheme:id,name')->orderBy('name'),
                'managedCenters' => fn ($query) => $query->with('scheme:id,name')->orderBy('name'),
            ])
            ->when($centerId !== null, function ($query) use ($centerId): void {
                $query->where(function ($inner) use ($centerId): void {
                    $inner->whereHas('headedCenters', fn ($centers) => $centers->where('centers.id', $centerId))
                        ->orWhereHas('managedCenters', fn ($centers) => $centers->where('centers.id', $centerId));
                });
            })
            ->when($visibleCenterIds !== null, function ($query) use ($visibleCenterIds): void {
                $query->where(function ($inner) use ($visibleCenterIds): void {
                    $inner->whereHas('headedCenters', fn ($centers) => $centers->whereIn('centers.id', $visibleCenterIds))
                        ->orWhereHas('managedCenters', fn ($centers) => $centers->whereIn('centers.id', $visibleCenterIds));
                });
            })
            ->when($schemeId !== null, function ($query) use ($schemeId): void {
                $query->where(function ($inner) use ($schemeId): void {
                    $inner->whereHas('headedCenters', fn ($centers) => $centers->where('centers.scheme_id', $schemeId))
                        ->orWhereHas('managedCenters', fn ($centers) => $centers->where('centers.scheme_id', $schemeId));
                });
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  list<int>  $excludeEmployeeIds
     * @return Collection<int, Employee>
     */
    private function fieldEmployees(User $viewer, ?int $centerId, ?int $schemeId, array $excludeEmployeeIds): Collection
    {
        return $this->access->employeeQuery($viewer)
            ->where('status', true)
            ->with(['center:id,name,scheme_id', 'center.scheme:id,name', 'user:id,employee_id,login_id,role,name'])
            ->when($centerId !== null, fn ($query) => $query->where('center_id', $centerId))
            ->when($schemeId !== null, fn ($query) => $query->whereHas('center', fn ($center) => $center->where('scheme_id', $schemeId)))
            ->when($excludeEmployeeIds !== [], fn ($query) => $query->whereNotIn('id', $excludeEmployeeIds))
            ->where(function ($query): void {
                $query->whereDoesntHave('user')
                    ->orWhereHas('user', fn ($user) => $user->where('role', UserRole::Employee->value));
            })
            ->orderBy('full_name')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function orgUserRow(User $user, ?Attendance $attendance): array
    {
        $centers = $user->isProjectHead() ? $user->headedCenters : $user->managedCenters;
        $center = $centers->first();

        return $this->personPayload(
            personKey: 'user:'.$user->id,
            userId: (int) $user->id,
            employeeId: $user->employee_id ? (int) $user->employee_id : null,
            name: $user->name,
            employeeCode: $user->employee?->employee_code,
            loginId: $user->login_id,
            role: $user->role,
            roleLabel: $user->roleEnum()->label(),
            schemeId: $center?->scheme_id,
            schemeName: $center?->scheme?->name,
            centerId: $center?->id,
            centerName: $center?->name,
            attendance: $attendance,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function employeeRow(Employee $employee, ?Attendance $attendance): array
    {
        $staffRole = $employee->staffRoleEnum();

        return $this->personPayload(
            personKey: 'employee:'.$employee->id,
            userId: $employee->user?->id ? (int) $employee->user->id : null,
            employeeId: (int) $employee->id,
            name: $employee->full_name,
            employeeCode: $employee->employee_code,
            loginId: $employee->user?->login_id,
            role: $staffRole->value,
            roleLabel: $staffRole->label(),
            schemeId: $employee->center?->scheme_id,
            schemeName: $employee->center?->scheme?->name,
            centerId: $employee->center_id,
            centerName: $employee->center?->name,
            attendance: $attendance,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function personPayload(
        string $personKey,
        ?int $userId,
        ?int $employeeId,
        string $name,
        ?string $employeeCode,
        ?string $loginId,
        string $role,
        string $roleLabel,
        mixed $schemeId,
        ?string $schemeName,
        mixed $centerId,
        ?string $centerName,
        ?Attendance $attendance,
    ): array {
        $status = 'not_punched_in';
        $statusLabel = 'Not Punched In';
        if ($attendance?->punch_in_time) {
            if (filled($attendance->punch_out_time)) {
                $status = 'punched_out';
                $statusLabel = 'Punched Out';
            } else {
                $status = 'punched_in';
                $statusLabel = 'Punched In';
            }
        }

        return [
            'id' => $employeeId ?? $userId,
            'person_key' => $personKey,
            'user_id' => $userId,
            'employee_id' => $employeeId,
            'full_name' => $name,
            'employee_code' => $employeeCode,
            'login_id' => $loginId,
            'role' => $role,
            'role_label' => $roleLabel,
            'staff_role' => $role,
            'staff_role_label' => $roleLabel,
            'scheme_id' => $schemeId ? (int) $schemeId : null,
            'scheme_name' => $schemeName,
            'project_name' => $schemeName,
            'center_id' => $centerId ? (int) $centerId : null,
            'center_name' => $centerName,
            'attendance_status' => $status,
            'attendance_status_label' => $statusLabel,
            'today_attendance_status' => $statusLabel,
        ];
    }

    private function roleRank(string $role): int
    {
        $index = array_search($role, self::ROLE_PRIORITY, true);

        return $index === false ? 100 : $index;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function roleFilterOptions(): array
    {
        return [
            ['value' => UserRole::ProjectHead->value, 'label' => UserRole::ProjectHead->label()],
            ['value' => UserRole::CenterManager->value, 'label' => UserRole::CenterManager->label()],
            ['value' => CenterStaffRole::Mis->value, 'label' => CenterStaffRole::Mis->label()],
            ['value' => CenterStaffRole::Trainer->value, 'label' => CenterStaffRole::Trainer->label()],
            ['value' => CenterStaffRole::Mobilizer->value, 'label' => CenterStaffRole::Mobilizer->label()],
            ['value' => 'other', 'label' => 'Other'],
        ];
    }
}
