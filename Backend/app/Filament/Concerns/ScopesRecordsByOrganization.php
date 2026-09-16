<?php

namespace App\Filament\Concerns;

use App\Models\Admission;
use App\Models\AdmissionTarget;
use App\Models\Attendance;
use App\Models\Center;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Scheme;
use App\Services\OrganizationAccessService;
use Illuminate\Database\Eloquent\Builder;

trait ScopesRecordsByOrganization
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        $access = app(OrganizationAccessService::class);
        $model = static::getModel();

        $ids = match ($model) {
            Scheme::class => $access->visibleSchemeIds($user),
            Center::class => $access->visibleCenterIds($user),
            Employee::class, Attendance::class, Admission::class, LeaveRequest::class, AdmissionTarget::class => $access->visibleEmployeeIds($user),
            default => null,
        };

        if ($ids === null) {
            return $query;
        }

        if ($model === Attendance::class) {
            return $query->whereIn('employee_id', $ids)->with(['employee.center.scheme']);
        }

        if ($model === Admission::class) {
            return $query->whereIn('employee_id', $ids)->with([
                'scheme',
                'employee.center.scheme',
                'district',
                'taluka',
            ]);
        }

        if ($model === LeaveRequest::class) {
            return $query->whereIn('employee_id', $ids)->with([
                'employee.center.scheme',
                'center',
                'scheme',
            ]);
        }

        if ($model === AdmissionTarget::class) {
            return $query->whereNull('parent_id')->whereIn('employee_id', $ids)->with([
                'employee.center.scheme',
                'center',
                'scheme',
                'weeks',
            ]);
        }

        $table = (new $model)->getTable();

        return $query->whereIn($table.'.id', $ids);
    }
}
