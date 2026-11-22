<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\User;
use App\Services\AdminCenterContext;
use App\Services\OrganizationAccessService;
use App\Support\AttendanceCalendar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One daily roster for every Filament role that can open Attendances.
 * Counts and the employee list share this query scope.
 */
final class DailyAttendanceBoard
{
    public const CARD_TOTAL = 'total';

    public const CARD_PUNCHED_IN = 'punched_in';

    public const CARD_PUNCHED_OUT = 'punched_out';

    public const CARD_CURRENTLY = 'currently';

    public const CARD_NOT_PUNCHED = 'not_punched_in';

    public const CARD_ON_LEAVE = 'on_leave';

    public const CARD_KEYS = [
        self::CARD_TOTAL,
        self::CARD_PUNCHED_IN,
        self::CARD_PUNCHED_OUT,
        self::CARD_CURRENTLY,
        self::CARD_NOT_PUNCHED,
        self::CARD_ON_LEAVE,
    ];

    /**
     * @return array{counts: array<string, int>, visible_ids: list<int>, closed: bool}
     */
    public function build(User $user, Carbon $date, ?int $schemeId, ?int $centerId, ?string $card): array
    {
        $date = $date->copy()->timezone(AttendanceCalendar::TIMEZONE)->startOfDay();
        $employeeIds = $this->rosterEmployeeIds($user, $date, $schemeId, $centerId);

        $calculator = app(AttendanceStatusCalculator::class);
        $closed = $calculator->isAttendanceDayClosed($date);
        $records = $this->recordsFor($employeeIds, $date);

        $counts = [
            self::CARD_TOTAL => count($employeeIds),
            self::CARD_PUNCHED_IN => 0,
            self::CARD_PUNCHED_OUT => 0,
            self::CARD_CURRENTLY => 0,
            self::CARD_NOT_PUNCHED => 0,
            self::CARD_ON_LEAVE => 0,
        ];
        $membership = [];

        foreach ($employeeIds as $employeeId) {
            $attendance = $this->best($records->get($employeeId, collect()));
            $cards = $this->cardsFor($attendance, $date, $calculator, $closed);
            $membership[$employeeId] = $cards;

            foreach ($cards as $key) {
                $counts[$key]++;
            }
        }

        $visible = $employeeIds;
        if (in_array($card, self::CARD_KEYS, true) && $card !== self::CARD_TOTAL) {
            $visible = array_values(array_filter(
                $employeeIds,
                fn (int $employeeId): bool => in_array($card, $membership[$employeeId], true),
            ));
        }

        return [
            'counts' => $counts,
            'visible_ids' => $visible,
            'closed' => $closed,
        ];
    }

    /**
     * Attendance rows for the date, plus one placeholder row per employee with no record.
     * Placeholder keys are negative employee ids so real attendance ids stay unchanged.
     *
     * @param  list<int>  $employeeIds
     */
    public function tableQuery(string $date, array $employeeIds): Builder
    {
        $query = Attendance::query()
            ->withoutGlobalScopes()
            ->from('employees')
            ->leftJoin('attendances', function ($join) use ($date): void {
                $join->on('attendances.employee_id', '=', 'employees.id')
                    ->whereDate('attendances.attendance_date', $date)
                    ->whereNull('attendances.deleted_at');
            })
            ->whereNull('employees.deleted_at')
            ->selectRaw(
                'COALESCE(attendances.id, -employees.id) as id, employees.id as employee_id, COALESCE(attendances.attendance_date, ?) as attendance_date, attendances.punch_in_time, attendances.punch_out_time, attendances.attendance_status, attendances.working_hours, attendances.total_working_minutes, attendances.approval_status',
                [$date],
            )
            ->with(['employee.center.scheme'])
            ->orderBy('employees.full_name');

        if ($employeeIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('employees.id', $employeeIds);
    }

    /**
     * @return array<string, array{label: string, hint: string, tone: string, icon: string}>
     */
    public function cardCatalog(bool $dayClosed): array
    {
        return [
            self::CARD_TOTAL => [
                'label' => 'Total Employees',
                'hint' => 'In scope',
                'tone' => 'lavender',
                'icon' => 'employees',
            ],
            self::CARD_PUNCHED_IN => [
                'label' => 'Punch In',
                'hint' => 'Has a punch in',
                'tone' => 'mint',
                'icon' => 'punched',
            ],
            self::CARD_PUNCHED_OUT => [
                'label' => 'Punch Out',
                'hint' => 'Has a punch out',
                'tone' => 'sky',
                'icon' => 'attendance',
            ],
            self::CARD_CURRENTLY => [
                'label' => 'Currently Punched In',
                'hint' => $dayClosed ? 'Day closed' : 'No punch out yet',
                'tone' => 'blue',
                'icon' => 'punched',
            ],
            self::CARD_NOT_PUNCHED => [
                'label' => 'Not Punched In Yet',
                'hint' => 'No punch in',
                'tone' => 'peach',
                'icon' => 'attendance',
            ],
            self::CARD_ON_LEAVE => [
                'label' => 'On Leave',
                'hint' => 'Leave status',
                'tone' => 'lilac',
                'icon' => 'leaves',
            ],
        ];
    }

    public function showsCenterFilter(User $user): bool
    {
        $count = count($this->centerOptions($user, null));

        if ($user->isAdminOrDirector()) {
            return $count > 0;
        }

        return $count > 1;
    }

    /**
     * @return array<int, string>
     */
    public function schemeOptions(User $user): array
    {
        if (! $user->isAdminOrDirector()) {
            return [];
        }

        return app(AdminCenterContext::class)
            ->scopeSchemes(app(OrganizationAccessService::class)->schemeQuery($user), $user)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function centerOptions(User $user, ?int $schemeId): array
    {
        $query = app(AdminCenterContext::class)
            ->scopeCenters(app(OrganizationAccessService::class)->centerQuery($user), $user)
            ->orderBy('name');

        if ($schemeId !== null && $user->isAdminOrDirector()) {
            $allowedSchemes = $this->schemeOptions($user);
            if (! array_key_exists($schemeId, $allowedSchemes)) {
                return [];
            }

            $query->where('scheme_id', $schemeId);
        }

        return $query->pluck('name', 'id')->all();
    }

    /**
     * Active field employees in scope, plus anyone in that scope who already
     * has an attendance row on the date so older records stay visible.
     *
     * @return list<int>
     */
    public function rosterEmployeeIds(User $user, Carbon $date, ?int $schemeId, ?int $centerId): array
    {
        $activeIds = $this->employees($user, $schemeId, $centerId, activeOnly: true)
            ->pluck('employees.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $recordedIds = Attendance::query()
            ->whereDate('attendance_date', $date->toDateString())
            ->whereIn('employee_id', $this->employees($user, $schemeId, $centerId, activeOnly: false)->select('employees.id'))
            ->pluck('employee_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $ids = array_values(array_unique([...$activeIds, ...$recordedIds]));
        sort($ids);

        return $ids;
    }

    public function employees(User $user, ?int $schemeId, ?int $centerId, bool $activeOnly = true): Builder
    {
        $access = app(OrganizationAccessService::class);
        $query = app(AdminCenterContext::class)->scopeEmployees(
            $activeOnly ? $access->fieldEmployeeQuery($user) : $access->employeeQuery($user),
            $user,
        );

        if ($user->isAdminOrDirector() && $schemeId !== null && array_key_exists($schemeId, $this->schemeOptions($user))) {
            $query->whereHas('center', fn (Builder $center): Builder => $center->where('scheme_id', $schemeId));
        }

        if ($centerId !== null && array_key_exists($centerId, $this->centerOptions($user, $user->isAdminOrDirector() ? $schemeId : null))) {
            $query->where('employees.center_id', $centerId);
        }

        return $query;
    }

    /**
     * @param  list<int>  $employeeIds
     * @return Collection<int, Collection<int, Attendance>>
     */
    private function recordsFor(array $employeeIds, Carbon $date): Collection
    {
        if ($employeeIds === []) {
            return collect();
        }

        return Attendance::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('attendance_date', $date->toDateString())
            ->get()
            ->groupBy(fn (Attendance $attendance): int => (int) $attendance->employee_id);
    }

    /**
     * @param  Collection<int, Attendance>  $rows
     */
    private function best(Collection $rows): ?Attendance
    {
        return $rows
            ->sortByDesc(fn (Attendance $attendance): int => $attendance->workingDurationMinutes() ?? -1)
            ->first();
    }

    /**
     * @return list<string>
     */
    private function cardsFor(?Attendance $attendance, Carbon $date, AttendanceStatusCalculator $calculator, bool $closed): array
    {
        $status = $calculator->classifyWorkingDay($attendance, $date);
        $hasIn = $attendance !== null && filled($attendance->punch_in_time);
        $hasOut = $attendance !== null && filled($attendance->punch_out_time);
        $cards = [];

        if ($hasIn) {
            $cards[] = self::CARD_PUNCHED_IN;
        }

        if ($hasOut) {
            $cards[] = self::CARD_PUNCHED_OUT;
        }

        if ($hasIn && ! $hasOut && ! $closed) {
            $cards[] = self::CARD_CURRENTLY;
        }

        if ($status === AttendanceStatusCalculator::STATUS_LEAVE) {
            $cards[] = self::CARD_ON_LEAVE;
        }

        if (
            ! $hasIn
            && ! in_array($status, [
                AttendanceStatusCalculator::STATUS_LEAVE,
                AttendanceStatusCalculator::STATUS_WEEKLY_OFF,
            ], true)
        ) {
            $cards[] = self::CARD_NOT_PUNCHED;
        }

        return $cards;
    }
}
