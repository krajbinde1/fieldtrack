<?php

namespace App\Filament\Widgets;

use App\Enums\AdmissionStatus;
use App\Enums\LeaveStatus;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Resources\Attendances\AttendanceResource;
use App\Filament\Resources\Centers\CenterResource;
use App\Filament\Resources\EmployeeRoutes\EmployeeRouteResource;
use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Filament\Resources\OrgUsers\OrgUserResource;
use App\Filament\Support\FilamentFilterUrl;
use App\Models\Attendance;
use App\Services\Attendance\AttendanceStatusCalculator;
use App\Services\DirectorWorkforceService;
use App\Services\OrganizationAccessService;
use App\Support\AttendanceCalendar;
use Filament\Widgets\Widget;

class DirectorAdminStatsWidget extends Widget
{
    protected string $view = 'filament.widgets.director-admin-stats-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return auth()->user()?->isAdminOrDirector() === true;
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
        $user = auth()->user();
        $access = app(OrganizationAccessService::class);
        $today = AttendanceCalendar::today()->toDateString();
        $monthStart = AttendanceCalendar::today()->copy()->startOfMonth()->toDateString();
        $employeeIds = $access->employeeQuery($user)->where('status', true)->pluck('id');
        $workforce = app(DirectorWorkforceService::class)->summarize($user);
        $employeeTotal = $workforce['total'];
        $punchedIn = $workforce['punched_in_today'];

        $activeRoutes = Attendance::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('attendance_date', $today)
            ->whereNotNull('punch_in_time')
            ->whereNull('punch_out_time')
            ->count();

        $pendingPhLeaves = $access->projectHeadLeaveQuery($user)
            ->where('status', LeaveStatus::Pending->value)
            ->count();

        $confirmedQuery = $access->admissionQuery($user)
            ->where('status', AdmissionStatus::Confirmed);
        $confirmedToday = (clone $confirmedQuery)->whereDate('confirmed_at', $today)->count();
        $confirmedMonth = (clone $confirmedQuery)
            ->whereDate('confirmed_at', '>=', $monthStart)
            ->whereDate('confirmed_at', '<=', $today)
            ->count();

        $todayAttendance = Attendance::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('attendance_date', $today);
        $present = (clone $todayAttendance)->where('attendance_status', AttendanceStatusCalculator::STATUS_PRESENT)->count();
        $halfDay = (clone $todayAttendance)->where('attendance_status', AttendanceStatusCalculator::STATUS_HALF_DAY)->count();
        $absent = (clone $todayAttendance)->where('attendance_status', AttendanceStatusCalculator::STATUS_ABSENT)->count();

        $todayDateFilter = ['attendance_date' => ['date' => $today]];

        return [
            [
                'label' => 'Total Centers',
                'value' => (string) $access->centerQuery($user)->count(),
                'hint' => null,
                'url' => CenterResource::getUrl(),
                'tone' => 'lavender',
                'icon' => 'centers',
            ],
            [
                'label' => 'Total Employees',
                'value' => (string) $employeeTotal,
                'hint' => null,
                'url' => OrgUserResource::getUrl(),
                'tone' => 'sky',
                'icon' => 'employees',
            ],
            [
                'label' => 'Punched In Today',
                'value' => $punchedIn.' / '.$employeeTotal,
                'hint' => 'Punched in / total employees',
                'url' => FilamentFilterUrl::for(AttendanceResource::class, [
                    ...$todayDateFilter,
                    'punched_in' => ['isActive' => true],
                ]),
                'tone' => 'mint',
                'icon' => 'punched',
            ],
            [
                'label' => 'Active Routes',
                'value' => (string) $activeRoutes,
                'hint' => null,
                'url' => FilamentFilterUrl::for(EmployeeRouteResource::class, [
                    ...$todayDateFilter,
                    'active_now' => ['isActive' => true],
                ]),
                'tone' => 'peach',
                'icon' => 'routes',
            ],
            [
                'label' => 'Pending Project Head Leaves',
                'value' => (string) $pendingPhLeaves,
                'hint' => null,
                'url' => FilamentFilterUrl::for(LeaveRequestResource::class, [
                    'status' => ['value' => LeaveStatus::Pending->value],
                    'project_head_only' => ['isActive' => true],
                ]),
                'tone' => 'amber',
                'icon' => 'leaves',
            ],
            [
                'label' => 'Today Confirmed Admissions',
                'value' => (string) $confirmedToday,
                'hint' => null,
                'url' => self::confirmedAdmissionsUrl($today, $today),
                'tone' => 'lilac',
                'icon' => 'admissions-today',
            ],
            [
                'label' => 'This Month Confirmed Admissions',
                'value' => (string) $confirmedMonth,
                'hint' => null,
                'url' => self::confirmedAdmissionsUrl($monthStart, $today),
                'tone' => 'blue',
                'icon' => 'admissions-month',
            ],
            [
                'label' => 'Attendance Today',
                'value' => $present.' / '.$halfDay.' / '.$absent,
                'hint' => 'Present / Half Day / Absent',
                'url' => FilamentFilterUrl::for(AttendanceResource::class, $todayDateFilter),
                'tone' => 'sage',
                'icon' => 'attendance',
            ],
        ];
    }

    private static function confirmedAdmissionsUrl(string $from, string $until): string
    {
        return FilamentFilterUrl::for(AdmissionResource::class, [
            'status' => ['value' => AdmissionStatus::Confirmed->value],
            'confirmed_range' => [
                'from' => $from,
                'until' => $until,
            ],
        ]);
    }
}
