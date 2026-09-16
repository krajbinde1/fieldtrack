<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\AdmissionTargetType;
use App\Models\Admission;
use App\Models\AdmissionTarget;
use App\Models\Employee;
use App\Models\User;
use App\Support\AdmissionTargetPeriod;
use App\Support\AttendanceCalendar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdmissionTargetService
{
    public function __construct(private readonly OrganizationAccessService $access) {}

    public function assign(User $user, array $payload): AdmissionTarget
    {
        $employee = Employee::query()->with('center')->findOrFail((int) $payload['employee_id']);
        abort_unless($employee->center_id && $employee->center, 422, 'Employee must belong to a center before a target can be assigned.');
        $this->access->assertCanAssignAdmissionTarget($user, $employee);

        $type = AdmissionTargetType::from($payload['target_type']);
        $count = max(0, (int) $payload['target_count']);
        $anchor = Carbon::parse($payload['period'], AttendanceCalendar::TIMEZONE)->startOfDay();

        if ($type === AdmissionTargetType::Monthly) {
            return $this->assignMonthly($user, $employee, $anchor, $count);
        }

        return $this->assignWeekly($user, $employee, $anchor, $count);
    }

    public function update(User $user, AdmissionTarget $target, array $payload): AdmissionTarget
    {
        $target->loadMissing('employee.center');
        abort_unless($target->parent_id === null, 422, 'Weekly splits are updated through the monthly target.');
        $this->access->assertCanAssignAdmissionTarget($user, $target->employee);

        $count = array_key_exists('target_count', $payload) ? max(0, (int) $payload['target_count']) : $target->target_count;
        $period = $payload['period'] ?? $target->period_start->toDateString();
        $type = isset($payload['target_type'])
            ? AdmissionTargetType::from($payload['target_type'])
            : $target->target_type;
        $anchor = Carbon::parse($period, AttendanceCalendar::TIMEZONE)->startOfDay();
        $employee = $target->employee;

        return DB::transaction(function () use ($user, $target, $employee, $type, $anchor, $count): AdmissionTarget {
            $target->delete();

            if ($type === AdmissionTargetType::Monthly) {
                return $this->assignMonthly($user, $employee, $anchor, $count);
            }

            return $this->assignWeekly($user, $employee, $anchor, $count);
        });
    }

    /**
     * @return Collection<int, AdmissionTarget>
     */
    public function listAssigned(User $user, array $filters = []): Collection
    {
        return $this->access->admissionTargetQuery($user)
            ->whereNull('parent_id')
            ->with(['employee', 'center', 'scheme', 'weeks'])
            ->when(isset($filters['scheme_id']), fn ($q) => $q->where('scheme_id', $filters['scheme_id']))
            ->when(isset($filters['project_id']), fn ($q) => $q->where('scheme_id', $filters['project_id']))
            ->when(isset($filters['center_id']), fn ($q) => $q->where('center_id', $filters['center_id']))
            ->when(isset($filters['employee_id']), fn ($q) => $q->where('employee_id', $filters['employee_id']))
            ->when(isset($filters['target_type']), fn ($q) => $q->where('target_type', $filters['target_type']))
            ->orderByDesc('period_start')
            ->limit(200)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function lookups(User $user): array
    {
        return [
            'schemes' => $this->access->schemeQuery($user)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name])
                ->values()
                ->all(),
            'projects' => $this->access->schemeQuery($user)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name])
                ->values()
                ->all(),
            'centers' => $this->access->centerQuery($user)
                ->orderBy('name')
                ->get(['id', 'name', 'scheme_id'])
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'name' => $row->name,
                    'scheme_id' => $row->scheme_id,
                    'project_id' => $row->scheme_id,
                ])
                ->values()
                ->all(),
            'employees' => $this->access->employeeQuery($user)
                ->with('center:id,name,scheme_id')
                ->where('status', true)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_code', 'center_id'])
                ->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'full_name' => $employee->full_name,
                    'employee_code' => $employee->employee_code,
                    'center_id' => $employee->center_id,
                    'center_name' => $employee->center?->name,
                    'scheme_id' => $employee->center?->scheme_id,
                    'project_id' => $employee->center?->scheme_id,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summaryForEmployee(Employee $employee, string $preset, ?string $from = null, ?string $to = null): array
    {
        [$start, $end] = AdmissionTargetPeriod::resolve($preset, $from, $to);

        return $this->metricsForEmployee($employee, $preset, $start, $end);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function performance(User $viewer, string $preset, ?string $from = null, ?string $to = null, array $filters = []): array
    {
        [$start, $end] = AdmissionTargetPeriod::resolve($preset, $from, $to);

        $employees = $this->access->employeeQuery($viewer)
            ->with(['center.scheme'])
            ->when(isset($filters['scheme_id']), fn ($q) => $q->whereHas('center', fn ($c) => $c->where('scheme_id', $filters['scheme_id'])))
            ->when(isset($filters['project_id']), fn ($q) => $q->whereHas('center', fn ($c) => $c->where('scheme_id', $filters['project_id'])))
            ->when(isset($filters['center_id']), fn ($q) => $q->where('center_id', $filters['center_id']))
            ->when(isset($filters['employee_id']), fn ($q) => $q->where('id', $filters['employee_id']))
            ->orderBy('full_name')
            ->get();

        return $employees
            ->map(fn (Employee $employee) => $this->metricsForEmployee($employee, $preset, $start, $end))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function metricsForEmployee(Employee $employee, string $preset, Carbon $start, Carbon $end): array
    {
        $target = $this->targetForPeriod($employee->id, $preset, $start, $end);
        $monthly = $this->monthlyCovering($employee->id, $start, $end);
        $weekly = $this->weeklyCovering($employee->id, $start, $end);
        $achieved = $this->achievedCount($employee->id, $start, $end);
        $remaining = max(0, $target - $achieved);
        $percentage = $target > 0 ? round(($achieved / $target) * 100, 1) : ($achieved > 0 ? 100.0 : 0.0);

        return [
            'employee_id' => $employee->id,
            'employee_name' => $employee->full_name,
            'employee_code' => $employee->employee_code,
            'center_id' => $employee->center_id,
            'center_name' => $employee->center?->name,
            'scheme_id' => $employee->center?->scheme_id,
            'scheme_name' => $employee->center?->scheme?->name,
            'project_id' => $employee->center?->scheme_id,
            'project_name' => $employee->center?->scheme?->name,
            'period' => [
                'preset' => $preset,
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
            'target' => $target,
            'monthly_target' => $monthly,
            'weekly_target' => $weekly,
            'achieved' => $achieved,
            'remaining' => $remaining,
            'percentage' => $percentage,
            'progress' => min(100, $percentage),
        ];
    }

    public function achievedCount(int $employeeId, Carbon $start, Carbon $end): int
    {
        return Admission::query()
            ->where('employee_id', $employeeId)
            ->where('status', AdmissionStatus::Confirmed)
            ->whereNotNull('submitted_at')
            ->whereDate('submitted_at', '>=', $start->toDateString())
            ->whereDate('submitted_at', '<=', $end->toDateString())
            ->count();
    }

    private function assignMonthly(User $user, Employee $employee, Carbon $anchor, int $count): AdmissionTarget
    {
        [$monthStart, $monthEnd] = AdmissionTargetPeriod::calendarMonth($anchor);
        $weeks = AdmissionTargetPeriod::weeksInMonth($monthStart);
        $parts = AdmissionTargetPeriod::allocateExactly($count, $weeks);

        return DB::transaction(function () use ($user, $employee, $monthStart, $monthEnd, $count, $parts): AdmissionTarget {
            $this->deleteOverlappingMonth($employee->id, $monthStart, $monthEnd);

            $parent = AdmissionTarget::query()->create([
                'employee_id' => $employee->id,
                'center_id' => $employee->center_id,
                'scheme_id' => $employee->center->scheme_id,
                'assigned_by_user_id' => $user->id,
                'parent_id' => null,
                'target_type' => AdmissionTargetType::Monthly,
                'period_start' => $monthStart->toDateString(),
                'period_end' => $monthEnd->toDateString(),
                'target_count' => $count,
            ]);

            foreach ($parts as $part) {
                AdmissionTarget::query()->create([
                    'employee_id' => $employee->id,
                    'center_id' => $employee->center_id,
                    'scheme_id' => $employee->center->scheme_id,
                    'assigned_by_user_id' => $user->id,
                    'parent_id' => $parent->id,
                    'target_type' => AdmissionTargetType::Weekly,
                    'period_start' => $part['start']->toDateString(),
                    'period_end' => $part['end']->toDateString(),
                    'target_count' => $part['count'],
                ]);
            }

            return $parent->fresh(['weeks', 'employee', 'center', 'scheme']);
        });
    }

    private function assignWeekly(User $user, Employee $employee, Carbon $anchor, int $count): AdmissionTarget
    {
        [$weekStart, $weekEnd] = AdmissionTargetPeriod::calendarWeek($anchor);

        $monthlyOverlap = AdmissionTarget::query()
            ->where('employee_id', $employee->id)
            ->whereNull('parent_id')
            ->where('target_type', AdmissionTargetType::Monthly)
            ->whereDate('period_start', '<=', $weekEnd->toDateString())
            ->whereDate('period_end', '>=', $weekStart->toDateString())
            ->exists();

        if ($monthlyOverlap) {
            throw ValidationException::withMessages([
                'period' => 'This week is covered by a monthly target. Update the monthly target instead.',
            ]);
        }

        return DB::transaction(function () use ($user, $employee, $weekStart, $weekEnd, $count): AdmissionTarget {
            AdmissionTarget::query()
                ->where('employee_id', $employee->id)
                ->whereNull('parent_id')
                ->where('target_type', AdmissionTargetType::Weekly)
                ->whereDate('period_start', $weekStart->toDateString())
                ->whereDate('period_end', $weekEnd->toDateString())
                ->delete();

            return AdmissionTarget::query()->create([
                'employee_id' => $employee->id,
                'center_id' => $employee->center_id,
                'scheme_id' => $employee->center->scheme_id,
                'assigned_by_user_id' => $user->id,
                'parent_id' => null,
                'target_type' => AdmissionTargetType::Weekly,
                'period_start' => $weekStart->toDateString(),
                'period_end' => $weekEnd->toDateString(),
                'target_count' => $count,
            ])->fresh(['employee', 'center', 'scheme']);
        });
    }

    private function deleteOverlappingMonth(int $employeeId, Carbon $monthStart, Carbon $monthEnd): void
    {
        $monthlies = AdmissionTarget::query()
            ->where('employee_id', $employeeId)
            ->whereNull('parent_id')
            ->where('target_type', AdmissionTargetType::Monthly)
            ->whereDate('period_start', $monthStart->toDateString())
            ->whereDate('period_end', $monthEnd->toDateString())
            ->get();

        foreach ($monthlies as $monthly) {
            $monthly->delete();
        }

        AdmissionTarget::query()
            ->where('employee_id', $employeeId)
            ->whereNull('parent_id')
            ->where('target_type', AdmissionTargetType::Weekly)
            ->whereDate('period_start', '<=', $monthEnd->toDateString())
            ->whereDate('period_end', '>=', $monthStart->toDateString())
            ->delete();
    }

    private function targetForPeriod(int $employeeId, string $preset, Carbon $start, Carbon $end): int
    {
        $isFullMonth = in_array($preset, ['this_month', 'month', 'last_month'], true)
            || ($start->isSameDay($start->copy()->startOfMonth()) && $end->isSameDay($end->copy()->endOfMonth()->startOfDay()));

        if ($isFullMonth) {
            $monthly = AdmissionTarget::query()
                ->where('employee_id', $employeeId)
                ->whereNull('parent_id')
                ->where('target_type', AdmissionTargetType::Monthly)
                ->whereDate('period_start', $start->copy()->startOfMonth()->toDateString())
                ->first();

            if ($monthly) {
                return (int) $monthly->target_count;
            }
        }

        $weeklies = $this->weeklyRowsOverlapping($employeeId, $start, $end);
        $total = 0;

        foreach ($weeklies as $row) {
            $periodDays = max(1, (int) $row->period_start->diffInDays($row->period_end) + 1);
            $overlap = AdmissionTargetPeriod::overlapDays($row->period_start, $row->period_end, $start, $end);
            if ($overlap <= 0) {
                continue;
            }
            if ($overlap === $periodDays) {
                $total += (int) $row->target_count;
            } else {
                $total += (int) round($row->target_count * ($overlap / $periodDays));
            }
        }

        return $total;
    }

    private function monthlyCovering(int $employeeId, Carbon $start, Carbon $end): ?int
    {
        $row = AdmissionTarget::query()
            ->where('employee_id', $employeeId)
            ->whereNull('parent_id')
            ->where('target_type', AdmissionTargetType::Monthly)
            ->whereDate('period_start', '<=', $end->toDateString())
            ->whereDate('period_end', '>=', $start->toDateString())
            ->orderByDesc('period_start')
            ->first();

        return $row?->target_count;
    }

    private function weeklyCovering(int $employeeId, Carbon $start, Carbon $end): ?int
    {
        $row = AdmissionTarget::query()
            ->where('employee_id', $employeeId)
            ->where('target_type', AdmissionTargetType::Weekly)
            ->whereDate('period_start', '<=', $end->toDateString())
            ->whereDate('period_end', '>=', $start->toDateString())
            ->orderByDesc('period_start')
            ->first();

        return $row?->target_count;
    }

    /**
     * @return Collection<int, AdmissionTarget>
     */
    private function weeklyRowsOverlapping(int $employeeId, Carbon $start, Carbon $end): Collection
    {
        return AdmissionTarget::query()
            ->where('employee_id', $employeeId)
            ->where('target_type', AdmissionTargetType::Weekly)
            ->whereDate('period_start', '<=', $end->toDateString())
            ->whereDate('period_end', '>=', $start->toDateString())
            ->get();
    }
}
