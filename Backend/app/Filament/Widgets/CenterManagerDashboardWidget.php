<?php

namespace App\Filament\Widgets;

use App\Enums\AdmissionStatus;
use App\Enums\AdmissionTargetType;
use App\Enums\LeaveStatus;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Resources\AdmissionTargets\AdmissionTargetResource;
use App\Filament\Resources\Attendances\AttendanceResource;
use App\Filament\Resources\EmployeeRoutes\EmployeeRouteResource;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Filament\Support\FilamentFilterUrl;
use App\Models\Attendance;
use App\Models\Center;
use App\Models\Employee;
use App\Models\FieldActivity;
use App\Models\User;
use App\Services\AdminCenterContext;
use App\Services\AdmissionTargetService;
use App\Services\Attendance\AttendanceStatusCalculator;
use App\Services\MobilizerPresenceService;
use App\Services\OrganizationAccessService;
use App\Support\AdmissionTargetPeriod;
use App\Support\AttendanceCalendar;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CenterManagerDashboardWidget extends Widget
{
    protected string $view = 'filament.widgets.center-manager-dashboard-widget';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public string $preset = 'today';

    public ?string $from = null;

    public ?string $to = null;

    public string $centerId = 'all';

    public static function canView(): bool
    {
        return auth()->user()?->isCenterManager() === true;
    }

    public function mount(): void
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        $selected = app(AdminCenterContext::class)->idFor($user);
        if ($selected !== null && $this->assignedCenters()->contains('id', $selected)) {
            $this->centerId = (string) $selected;
        }
    }

    public function setPreset(string $preset): void
    {
        if (! in_array($preset, ['today', 'this_week', 'this_month', 'custom'], true)) {
            return;
        }

        $this->preset = $preset;
        if ($preset === 'custom') {
            $today = AttendanceCalendar::today()->toDateString();
            $this->from = $this->from ?: $today;
            $this->to = $this->to ?: $today;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $user->isCenterManager()) {
            return $this->emptyDashboard();
        }

        $centers = $this->assignedCenters();
        $centerId = $this->selectedCenterId($centers);
        [$start, $end] = $this->period();
        $focus = $this->focusDate($start, $end);
        $employees = $this->employees($user, $centerId);
        $employeeIds = $employees->pluck('id');
        $attendance = $this->attendanceTotals($employeeIds, $start, $end);
        $filters = $centerId === null ? [] : ['center_id' => $centerId];
        $targets = app(AdmissionTargetService::class);
        $monthly = $targets->teamPerformance($user, 'this_month', null, null, $filters);
        $periodPreset = $this->preset === 'today' ? 'this_month' : $this->preset;
        $periodTarget = $periodPreset === 'this_month'
            ? $monthly
            : $targets->teamPerformance($user, $periodPreset, $start->toDateString(), $end->toDateString(), $filters);
        $confirmed = $this->confirmedCount($user, $centerId, $start, $end);
        $pendingAdmissions = $this->pendingAdmissions($user, $centerId);
        $pendingLeaves = $this->pendingLeaves($user, $centerId);

        return [
            'manager' => $user->name,
            'centers' => $centers->pluck('name')->all(),
            'centerOptions' => $centers->map(fn ($center): array => ['id' => (string) $center->id, 'name' => $center->name])->all(),
            'showCenterSelect' => $centers->count() > 1,
            'selectedCenter' => $centerId === null
                ? 'All Assigned Centers'
                : (string) ($centers->firstWhere('id', $centerId)?->name ?? 'All Assigned Centers'),
            'todayLabel' => AttendanceCalendar::today()->format('l, d M Y'),
            'periodLabel' => $this->periodLabel($start, $end),
            'cards' => $this->cards($employees->count(), $attendance, $monthly, $periodTarget, $confirmed, $pendingAdmissions->count(), $pendingLeaves->count(), $centerId, $start, $end, $focus),
            'chart' => $this->chart($user, $filters, $start, $end),
            'attendanceRows' => $this->attendanceRows($employees, $focus),
            'attendanceUrl' => $this->attendanceUrl($focus, null, $centerId),
            'pendingAdmissions' => $pendingAdmissions->take(6)->map(fn ($row): array => [
                'name' => $row->full_name,
                'employee' => $row->employee?->full_name ?? '—',
                'center' => $row->center?->name ?? '—',
                'when' => $row->submitted_at?->timezone(AttendanceCalendar::TIMEZONE)->format('d M Y') ?? '—',
            ])->all(),
            'pendingAdmissionsUrl' => $this->admissionUrl(AdmissionStatus::Submitted->value, null, null, $centerId),
            'pendingLeaves' => $pendingLeaves->take(6)->map(fn ($row): array => [
                'name' => $row->employee?->full_name ?? '—',
                'type' => $row->leave_type?->label() ?? '—',
                'dates' => ($row->from_date?->format('d M') ?? '—').' – '.($row->to_date?->format('d M') ?? '—'),
            ])->all(),
            'pendingLeavesUrl' => $this->leaveUrl($centerId),
            'activities' => $this->activities($user, $centerId, $start, $end),
            'presenceRows' => app(MobilizerPresenceService::class)->dashboardRows($user, $centerId),
            'routesUrl' => FilamentFilterUrl::for(EmployeeRouteResource::class, [
                'attendance_date' => ['date' => $focus->toDateString()],
            ]),
        ];
    }

    /**
     * @return Collection<int, Center>
     */
    private function assignedCenters(): Collection
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return collect();
        }

        return app(OrganizationAccessService::class)->centerQuery($user)->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @param  Collection<int, Center>  $centers
     */
    private function selectedCenterId(Collection $centers): ?int
    {
        if ($this->centerId === 'all' || ! ctype_digit($this->centerId)) {
            return null;
        }

        $id = (int) $this->centerId;

        return $centers->contains('id', $id) ? $id : null;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(): array
    {
        if ($this->preset === 'custom' && filled($this->from) && filled($this->to)) {
            [$start, $end] = AdmissionTargetPeriod::resolve('custom', $this->from, $this->to);
        } else {
            $preset = in_array($this->preset, ['today', 'this_week', 'this_month'], true) ? $this->preset : 'today';
            [$start, $end] = AdmissionTargetPeriod::resolve($preset);
        }

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        if ($start->diffInDays($end) > 62) {
            $end = $start->copy()->addDays(62);
        }

        return [$start, $end];
    }

    private function focusDate(Carbon $start, Carbon $end): Carbon
    {
        $today = AttendanceCalendar::today();
        if ($today->betweenIncluded($start, $end)) {
            return $today;
        }

        return $end->copy();
    }

    /**
     * @return Collection<int, Employee>
     */
    private function employees(User $user, ?int $centerId): Collection
    {
        return app(OrganizationAccessService::class)
            ->fieldEmployeeQuery($user)
            ->when($centerId !== null, fn ($query) => $query->where('center_id', $centerId))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'center_id']);
    }

    /**
     * @param  Collection<int, int>  $employeeIds
     * @return array{present: int, absent: int, on_leave: int}
     */
    private function attendanceTotals(Collection $employeeIds, Carbon $start, Carbon $end): array
    {
        $calculator = app(AttendanceStatusCalculator::class);
        $totals = ['present' => 0, 'absent' => 0, 'on_leave' => 0];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $tally = $calculator->tallyEmployees($employeeIds, $day->copy());
            $totals['present'] += $tally['present'] + $tally['half_day'] + $tally['running'];
            $totals['absent'] += $tally['absent'];
            $totals['on_leave'] += $tally['on_leave'];
        }

        return $totals;
    }

    private function confirmedCount(User $user, ?int $centerId, Carbon $start, Carbon $end): int
    {
        return app(OrganizationAccessService::class)
            ->admissionQuery($user)
            ->when($centerId !== null, fn ($query) => $query->where('center_id', $centerId))
            ->where('status', AdmissionStatus::Confirmed)
            ->whereDate('confirmed_at', '>=', $start->toDateString())
            ->whereDate('confirmed_at', '<=', $end->toDateString())
            ->count();
    }

    private function pendingAdmissions(User $user, ?int $centerId): Collection
    {
        return app(OrganizationAccessService::class)
            ->admissionQuery($user)
            ->with(['employee:id,full_name', 'center:id,name'])
            ->when($centerId !== null, fn ($query) => $query->where('center_id', $centerId))
            ->where('status', AdmissionStatus::Submitted)
            ->latest('submitted_at')
            ->get(['id', 'full_name', 'employee_id', 'center_id', 'submitted_at']);
    }

    private function pendingLeaves(User $user, ?int $centerId): Collection
    {
        return app(OrganizationAccessService::class)
            ->leaveQuery($user)
            ->with('employee:id,full_name')
            ->when($centerId !== null, fn ($query) => $query->where('center_id', $centerId))
            ->where('status', LeaveStatus::Pending->value)
            ->orderBy('from_date')
            ->get(['id', 'employee_id', 'leave_type', 'from_date', 'to_date']);
    }

    /**
     * @param  array{present: int, absent: int, on_leave: int}  $attendance
     * @param  array{target: int, achieved: int, percentage: float}  $monthly
     * @param  array{target: int, achieved: int, percentage: float}  $periodTarget
     * @return list<array{label: string, value: string, hint: ?string, url: string, tone: string, icon: string}>
     */
    private function cards(int $employees, array $attendance, array $monthly, array $periodTarget, int $confirmed, int $pendingAdmissions, int $pendingLeaves, ?int $centerId, Carbon $start, Carbon $end, Carbon $focus): array
    {
        $periodHint = $this->periodLabel($start, $end);

        return [
            $this->card('Total Employees', (string) $employees, 'Active employees', EmployeeResource::getUrl(), 'lavender', 'employees'),
            $this->card('Present Today', (string) $attendance['present'], $periodHint, $this->attendanceUrl($focus, null, $centerId), 'mint', 'punched'),
            $this->card('Absent Today', (string) $attendance['absent'], $periodHint, $this->attendanceUrl($focus, AttendanceStatusCalculator::STATUS_ABSENT, $centerId), 'peach', 'attendance'),
            $this->card('On Leave Today', (string) $attendance['on_leave'], $periodHint, $this->attendanceUrl($focus, AttendanceStatusCalculator::STATUS_LEAVE, $centerId), 'sky', 'leaves'),
            $this->card('Monthly Admission Target', (string) $monthly['target'], 'This month', $this->targetUrl('this_month', null, null, $centerId, AdmissionTargetType::Monthly->value), 'lilac', 'admissions-month'),
            $this->card('Confirmed Admissions', (string) $confirmed, $periodHint, $this->admissionUrl(AdmissionStatus::Confirmed->value, $start->toDateString(), $end->toDateString(), $centerId), 'sage', 'admissions-today'),
            $this->card('Pending Admissions', (string) $pendingAdmissions, 'Awaiting approval', $this->admissionUrl(AdmissionStatus::Submitted->value, null, null, $centerId), 'amber', 'admissions-today'),
            $this->card('Target Achievement %', $this->percent($periodTarget['percentage']), $this->preset === 'today' || $this->preset === 'this_month' ? 'This month' : $periodHint, $this->targetUrl($this->preset === 'today' ? 'this_month' : $this->preset, $start->toDateString(), $end->toDateString(), $centerId, null), 'blue', 'attendance'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{label: string, target: int, achieved: int}>
     */
    private function chart(User $user, array $filters, Carbon $start, Carbon $end): array
    {
        $targets = app(AdmissionTargetService::class);
        if ($this->preset === 'this_week') {
            $metrics = $targets->teamPerformance($user, 'this_week', null, null, $filters);

            return [[
                'label' => 'This week',
                'target' => $metrics['target'],
                'achieved' => $metrics['achieved'],
            ]];
        }

        if ($this->preset === 'custom') {
            $metrics = $targets->teamPerformance($user, 'custom', $start->toDateString(), $end->toDateString(), $filters);

            return [[
                'label' => $start->format('d M').' – '.$end->format('d M'),
                'target' => $metrics['target'],
                'achieved' => $metrics['achieved'],
            ]];
        }

        $bars = [];
        foreach (AdmissionTargetPeriod::weeksInMonth($start->copy()->startOfMonth()) as $index => $week) {
            $metrics = $targets->teamPerformance(
                $user,
                'custom',
                $week['start']->toDateString(),
                $week['end']->toDateString(),
                $filters,
            );
            $bars[] = [
                'label' => 'Week '.($index + 1),
                'target' => $metrics['target'],
                'achieved' => $metrics['achieved'],
            ];
        }

        return $bars;
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return list<array{name: string, punch_in: string, punch_out: string, status: string}>
     */
    private function attendanceRows(Collection $employees, Carbon $focus): array
    {
        $calculator = app(AttendanceStatusCalculator::class);
        $records = Attendance::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereDate('attendance_date', $focus->toDateString())
            ->get()
            ->groupBy(fn (Attendance $attendance): int => (int) $attendance->employee_id);

        return $employees->take(12)->map(function (Employee $employee) use ($records, $calculator, $focus): array {
            $attendance = $records->get($employee->id, collect())
                ->sortByDesc(fn (Attendance $row): int => $row->workingDurationMinutes() ?? -1)
                ->first();
            $status = $calculator->classifyWorkingDay($attendance, $focus);

            return [
                'name' => $employee->full_name,
                'punch_in' => $attendance?->punchInAt()?->timezone(AttendanceCalendar::TIMEZONE)->format('h:i A') ?? '—',
                'punch_out' => $attendance?->punchOutAt()?->timezone(AttendanceCalendar::TIMEZONE)->format('h:i A') ?? '—',
                'status' => AttendanceStatusCalculator::statusLabels()[$status] ?? $status,
            ];
        })->all();
    }

    /**
     * @return list<array{name: string, activity: string, when: string}>
     */
    private function activities(User $user, ?int $centerId, Carbon $start, Carbon $end): array
    {
        return app(OrganizationAccessService::class)
            ->fieldActivityQuery($user)
            ->with('employee:id,full_name')
            ->when($centerId !== null, fn ($query) => $query->where('center_id', $centerId))
            ->whereDate('activity_at', '>=', $start->toDateString())
            ->whereDate('activity_at', '<=', $end->toDateString())
            ->latest('activity_at')
            ->limit(6)
            ->get()
            ->map(fn (FieldActivity $activity): array => [
                'name' => $activity->employee?->full_name ?? '—',
                'activity' => $activity->activity_type?->label() ?? ($activity->activity_name ?: 'Activity'),
                'when' => $activity->activity_at?->timezone(AttendanceCalendar::TIMEZONE)->format('d M, h:i A') ?? '—',
            ])
            ->all();
    }

    /**
     * @return array{label: string, value: string, hint: ?string, url: string, tone: string, icon: string}
     */
    private function card(string $label, string $value, ?string $hint, string $url, string $tone, string $icon): array
    {
        return compact('label', 'value', 'hint', 'url', 'tone', 'icon');
    }

    private function attendanceUrl(Carbon $focus, ?string $status, ?int $centerId): string
    {
        $filters = ['attendance_date' => ['date' => $focus->toDateString()]];
        if ($status !== null) {
            $filters['attendance_status'] = ['value' => $status];
        }

        return FilamentFilterUrl::for(AttendanceResource::class, $filters);
    }

    private function admissionUrl(string $status, ?string $from, ?string $until, ?int $centerId): string
    {
        $filters = ['status' => ['value' => $status]];
        if ($from !== null && $until !== null) {
            $filters['confirmed_range'] = ['from' => $from, 'until' => $until];
        }
        if ($centerId !== null) {
            $filters['center_id'] = ['value' => $centerId];
        }

        return FilamentFilterUrl::for(AdmissionResource::class, $filters);
    }

    private function leaveUrl(?int $centerId): string
    {
        $filters = ['status' => ['value' => LeaveStatus::Pending->value]];
        if ($centerId !== null) {
            $filters['center_id'] = ['value' => $centerId];
        }

        return FilamentFilterUrl::for(LeaveRequestResource::class, $filters);
    }

    private function targetUrl(string $preset, ?string $from, ?string $until, ?int $centerId, ?string $type): string
    {
        $filters = ['period' => ['preset' => $preset === 'today' ? 'this_month' : $preset]];
        if ($preset === 'custom' && $from !== null && $until !== null) {
            $filters['period']['from'] = $from;
            $filters['period']['until'] = $until;
        }
        if ($centerId !== null) {
            $filters['center_id'] = ['value' => $centerId];
        }
        if ($type !== null) {
            $filters['target_type'] = ['value' => $type];
        }

        return FilamentFilterUrl::for(AdmissionTargetResource::class, $filters);
    }

    private function periodLabel(Carbon $start, Carbon $end): string
    {
        if ($start->isSameDay($end)) {
            return $start->isSameDay(AttendanceCalendar::today()) ? 'Today' : $start->format('d M Y');
        }

        return $start->format('d M').' – '.$end->format('d M Y');
    }

    private function percent(float $value): string
    {
        $text = number_format($value, 1, '.', '');

        return rtrim(rtrim($text, '0'), '.').'%';
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDashboard(): array
    {
        return [
            'manager' => '',
            'centers' => [],
            'centerOptions' => [],
            'showCenterSelect' => false,
            'selectedCenter' => '',
            'todayLabel' => '',
            'periodLabel' => '',
            'cards' => [],
            'chart' => [],
            'attendanceRows' => [],
            'attendanceUrl' => '#',
            'pendingAdmissions' => [],
            'pendingAdmissionsUrl' => '#',
            'pendingLeaves' => [],
            'pendingLeavesUrl' => '#',
            'activities' => [],
            'presenceRows' => [],
            'routesUrl' => '#',
        ];
    }
}
