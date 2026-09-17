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
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DirectorAdminStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected int|array|null $columns = 4;

    public static function canView(): bool
    {
        return auth()->user()?->isAdminOrDirector() === true;
    }

    protected function getStats(): array
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
            Stat::make('Total Centers', (string) $access->centerQuery($user)->count())
                ->url(CenterResource::getUrl()),
            Stat::make('Total Employees', (string) $employeeTotal)
                ->url(OrgUserResource::getUrl()),
            Stat::make('Punched In Today', $punchedIn.' / '.$employeeTotal)
                ->description('Punched in / total employees')
                ->url(FilamentFilterUrl::for(AttendanceResource::class, [
                    ...$todayDateFilter,
                    'punched_in' => ['isActive' => true],
                ])),
            Stat::make('Active Routes', (string) $activeRoutes)
                ->url(FilamentFilterUrl::for(EmployeeRouteResource::class, [
                    ...$todayDateFilter,
                    'active_now' => ['isActive' => true],
                ])),
            Stat::make('Pending Project Head Leaves', (string) $pendingPhLeaves)
                ->url(FilamentFilterUrl::for(LeaveRequestResource::class, [
                    'status' => ['value' => LeaveStatus::Pending->value],
                    'project_head_only' => ['isActive' => true],
                ])),
            Stat::make('Today Confirmed Admissions', (string) $confirmedToday)
                ->url(self::confirmedAdmissionsUrl($today, $today)),
            Stat::make('This Month Confirmed Admissions', (string) $confirmedMonth)
                ->url(self::confirmedAdmissionsUrl($monthStart, $today)),
            Stat::make('Attendance Today', $present.' / '.$halfDay.' / '.$absent)
                ->description('Present / Half Day / Absent')
                ->url(FilamentFilterUrl::for(AttendanceResource::class, $todayDateFilter)),
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
