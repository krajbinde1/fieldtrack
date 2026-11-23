<?php

namespace App\Filament\Widgets;

use App\Enums\AdmissionStatus;
use App\Enums\LeaveStatus;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Resources\Attendances\AttendanceResource;
use App\Filament\Resources\Centers\CenterResource;
use App\Filament\Resources\EmployeeRoutes\EmployeeRouteResource;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Filament\Resources\OrgUsers\OrgUserResource;
use App\Filament\Support\FilamentFilterUrl;
use App\Filament\Widgets\Concerns\UsesOrganizationWideDashboardScope;
use App\Models\Attendance;
use App\Models\User;
use App\Services\AdminCenterContext;
use App\Services\Attendance\AttendanceStatusCalculator;
use App\Services\DirectorWorkforceService;
use App\Services\OrganizationAccessService;
use App\Support\AttendanceCalendar;
use Filament\Widgets\Widget;

class DirectorAdminStatsWidget extends Widget
{
    use UsesOrganizationWideDashboardScope;

    private bool $resolvingOrganizationWideCards = false;

    protected string $view = 'filament.widgets.director-admin-stats-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && ($user->isAdminOrDirector() || $user->isCenterCoordinator());
    }

    /**
     * @return list<array{
     *     label: string,
     *     value: string,
     *     hint: ?string,
     *     url: ?string,
     *     tone: string,
     *     icon: string
     * }>
     */
    public function getCards(): array
    {
        if ($this->organizationWide && ! $this->resolvingOrganizationWideCards) {
            $this->resolvingOrganizationWideCards = true;

            try {
                return app(AdminCenterContext::class)->duringOrganizationWideScope(
                    fn (): array => $this->getCards(),
                );
            } finally {
                $this->resolvingOrganizationWideCards = false;
            }
        }

        $user = auth()->user();
        $access = app(OrganizationAccessService::class);
        $context = app(AdminCenterContext::class);
        $today = AttendanceCalendar::today()->toDateString();
        $monthStart = AttendanceCalendar::today()->copy()->startOfMonth()->toDateString();
        $employeeIds = $context->scopeEmployees(
            $access->employeeQuery($user)->where('status', true),
            $user,
        )->pluck('id');
        $workforce = app(DirectorWorkforceService::class)->summarize($user, $context->workforceCenterId($user));
        $employeeTotal = $workforce['total'];
        $punchedIn = $workforce['punched_in_today'];

        $activeRoutes = Attendance::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('attendance_date', $today)
            ->whereNotNull('punch_in_time')
            ->whereNull('punch_out_time')
            ->count();

        $confirmedQuery = $context->scopeByCenterId($access->admissionQuery($user), $user)
            ->where('status', AdmissionStatus::Confirmed);
        $confirmedToday = (clone $confirmedQuery)->whereDate('confirmed_at', $today)->count();
        $confirmedMonth = (clone $confirmedQuery)
            ->whereDate('confirmed_at', '>=', $monthStart)
            ->whereDate('confirmed_at', '<=', $today)
            ->count();

        $todayDateFilter = ['attendance_date' => ['date' => $today]];
        // Null while the main dashboard runs in organization-wide scope.
        $centerId = $user instanceof User ? $context->idFor($user) : null;
        $attendanceEmployees = $user instanceof User
            ? $access->fieldEmployeeQuery($user)
            : $access->employeeQuery($user)->where('status', true);
        if ($centerId !== null) {
            $attendanceEmployees->where('center_id', $centerId);
        }
        $attendanceTally = app(AttendanceStatusCalculator::class)->tallyEmployees(
            $attendanceEmployees->pluck('id'),
            AttendanceCalendar::today(),
        );
        $present = $attendanceTally['present'];
        $halfDay = $attendanceTally['half_day'];
        $absent = $attendanceTally['absent'];
        $running = $attendanceTally['running'];
        $onLeave = $attendanceTally['on_leave'];

        $employeesCard = [
            'label' => 'Total Employees',
            'value' => (string) $employeeTotal,
            'hint' => null,
            'url' => $user instanceof User && $user->isCenterCoordinator()
                ? EmployeeResource::getUrl()
                : OrgUserResource::getUrl(),
            'tone' => 'sky',
            'icon' => 'employees',
        ];

        if ($centerId !== null && $user instanceof User) {
            if (! $user->isCenterCoordinator()) {
                $centerEmployeeIds = $access->fieldEmployeeQuery($user)
                    ->where('center_id', $centerId)
                    ->pluck('id');
                $employeeTotal = $centerEmployeeIds->count();
                $punchedIn = $centerEmployeeIds->isEmpty()
                    ? 0
                    : Attendance::query()
                        ->whereIn('employee_id', $centerEmployeeIds)
                        ->whereDate('attendance_date', $today)
                        ->whereNotNull('punch_in_time')
                        ->pluck('employee_id')
                        ->unique()
                        ->count();
                $activeRoutes = $centerEmployeeIds->isEmpty()
                    ? 0
                    : Attendance::query()
                        ->whereIn('employee_id', $centerEmployeeIds)
                        ->whereDate('attendance_date', $today)
                        ->whereNotNull('punch_in_time')
                        ->whereNull('punch_out_time')
                        ->count();
                $employeesCard['value'] = (string) $employeeTotal;
            }
            $employeesCard['url'] = $user->isCenterCoordinator()
                ? EmployeeResource::getUrl('index', ['center' => $centerId])
                : OrgUserResource::getUrl('index', [
                    'employees' => 1,
                    'center' => $centerId,
                ]);
        }
        $punchedCard = [
            'label' => 'Punched In Today',
            'value' => $punchedIn.' / '.$employeeTotal,
            'hint' => 'Punched in / total employees',
            'url' => self::centerDashboardUrl(AttendanceResource::class, [
                ...$todayDateFilter,
                'punched_in' => ['isActive' => true],
            ], $centerId),
            'tone' => 'mint',
            'icon' => 'punched',
        ];
        $routesCard = [
            'label' => 'Active Routes',
            'value' => (string) $activeRoutes,
            'hint' => null,
            'url' => self::centerDashboardUrl(EmployeeRouteResource::class, [
                ...$todayDateFilter,
                'active_now' => ['isActive' => true],
            ], $centerId),
            'tone' => 'peach',
            'icon' => 'routes',
        ];
        $attendanceCard = [
            'label' => 'Attendance Today',
            'value' => $present.' / '.$halfDay.' / '.$absent.' / '.$running.' / '.$onLeave,
            'hint' => 'Present / Half Day / Absent / Running / On Leave',
            'url' => self::centerDashboardUrl(AttendanceResource::class, $todayDateFilter, $centerId),
            'tone' => 'sage',
            'icon' => 'attendance',
        ];
        $confirmedTodayCard = [
            'label' => 'Today Confirmed Admissions',
            'value' => (string) $confirmedToday,
            'hint' => null,
            'url' => self::confirmedAdmissionsUrl($today, $today, $centerId),
            'tone' => 'lilac',
            'icon' => 'admissions-today',
        ];
        $confirmedMonthCard = [
            'label' => 'This Month Confirmed Admissions',
            'value' => (string) $confirmedMonth,
            'hint' => null,
            'url' => self::confirmedAdmissionsUrl($monthStart, $today, $centerId),
            'tone' => 'blue',
            'icon' => 'admissions-month',
        ];

        if ($centerId !== null) {
            $totalAdmissions = $context->scopeByCenterId($access->admissionQuery($user), $user)->count();
            $pendingLeaves = $context->scopeByCenterId($access->leaveQuery($user), $user)
                ->where('status', LeaveStatus::Pending->value)
                ->count();

            return [
                $employeesCard,
                $punchedCard,
                $routesCard,
                $attendanceCard,
                [
                    'label' => 'Total Admissions',
                    'value' => (string) $totalAdmissions,
                    'hint' => null,
                    'url' => self::centerDashboardUrl(AdmissionResource::class, [], $centerId, true),
                    'tone' => 'lilac',
                    'icon' => 'admissions-today',
                ],
                $confirmedTodayCard,
                $confirmedMonthCard,
                [
                    'label' => 'Pending Leave Requests',
                    'value' => (string) $pendingLeaves,
                    'hint' => null,
                    'url' => self::centerDashboardUrl(LeaveRequestResource::class, [
                        'status' => ['value' => LeaveStatus::Pending->value],
                    ], $centerId, true),
                    'tone' => 'amber',
                    'icon' => 'leaves',
                ],
            ];
        }

        return [
            [
                'label' => 'Total Centers',
                'value' => (string) $access->centerQuery($user)->count(),
                'hint' => null,
                'url' => CenterResource::getUrl(),
                'tone' => 'lavender',
                'icon' => 'centers',
            ],
            $employeesCard,
            $punchedCard,
            $routesCard,
            [
                'label' => 'Pending Leave Requests',
                'value' => (string) $context->scopeByCenterId($access->projectHeadLeaveQuery($user), $user)
                    ->where('status', LeaveStatus::Pending->value)
                    ->count(),
                'hint' => null,
                'url' => FilamentFilterUrl::for(LeaveRequestResource::class, [
                    'status' => ['value' => LeaveStatus::Pending->value],
                    'project_head_only' => ['isActive' => true],
                ]),
                'tone' => 'amber',
                'icon' => 'leaves',
            ],
            $confirmedTodayCard,
            $confirmedMonthCard,
            $attendanceCard,
        ];
    }

    private static function confirmedAdmissionsUrl(string $from, string $until, ?int $centerId = null): string
    {
        $filters = [
            'status' => ['value' => AdmissionStatus::Confirmed->value],
            'confirmed_range' => [
                'from' => $from,
                'until' => $until,
            ],
        ];

        if ($centerId !== null) {
            $filters['center_id'] = ['value' => $centerId];
        }

        return FilamentFilterUrl::for(AdmissionResource::class, $filters);
    }

    /**
     * @param  class-string  $resource
     * @param  array<string, mixed>  $filters
     */
    private static function centerDashboardUrl(string $resource, array $filters, ?int $centerId, bool $centerFilter = false): string
    {
        if ($centerId !== null && $centerFilter) {
            $filters['center_id'] = ['value' => $centerId];
        }

        $parameters = $filters === [] ? [] : ['filters' => $filters];

        if ($centerId !== null) {
            $parameters['center'] = $centerId;
        }

        return $resource::getUrl('index', $parameters);
    }
}
