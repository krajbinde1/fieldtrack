<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Services\AdminCenterContext;
use App\Services\OrganizationAccessService;
use App\Support\AttendanceCalendar;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FieldTrackStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isProjectHead() || $user?->isCenterManager());
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $access = app(OrganizationAccessService::class);
        $today = AttendanceCalendar::today()->toDateString();
        $centerId = $user instanceof \App\Models\User
            ? app(AdminCenterContext::class)->idFor($user)
            : null;
        $employeeIds = $access->employeeQuery($user)->where('status', true);
        if ($centerId !== null) {
            $employeeIds->where('center_id', $centerId);
        }
        $employeeIds = $employeeIds->pluck('id');

        $punchedIn = Attendance::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('attendance_date', $today)
            ->whereNotNull('punch_in_time')
            ->count();

        $active = Attendance::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('attendance_date', $today)
            ->whereNotNull('punch_in_time')
            ->whereNull('punch_out_time')
            ->count();

        return [
            Stat::make('Schemes', (string) ($centerId === null
                ? $access->schemeQuery($user)->count()
                : $access->centerQuery($user)->whereKey($centerId)->distinct()->count('scheme_id'))),
            Stat::make('Centers', (string) ($centerId === null
                ? $access->centerQuery($user)->count()
                : $access->centerQuery($user)->whereKey($centerId)->count())),
            Stat::make('Employees', (string) $employeeIds->count()),
            Stat::make('Punched In Today', (string) $punchedIn),
            Stat::make('Active Routes', (string) $active),
        ];
    }
}
