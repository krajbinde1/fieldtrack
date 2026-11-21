<?php

namespace App\Filament\Concerns;

use App\Models\Admission;
use App\Models\AdmissionTarget;
use App\Models\Attendance;
use App\Models\Center;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Scheme;
use App\Services\AdminCenterContext;
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
            Admission::class => $access->visibleCenterIds($user),
            Employee::class, Attendance::class, LeaveRequest::class, AdmissionTarget::class => $access->visibleEmployeeIds($user),
            default => null,
        };

        if ($ids === null) {
            $scoped = $query;
        } elseif ($model === Attendance::class) {
            $scoped = $query->whereIn('employee_id', $ids)->with(['employee.center.scheme']);
        } elseif ($model === Admission::class) {
            $scoped = $query->whereIn('center_id', $ids)->with([
                'scheme',
                'employee.center.scheme',
                'district',
                'taluka',
                'reviewedBy',
            ]);
        } elseif ($model === LeaveRequest::class) {
            $scoped = $query->whereIn('employee_id', $ids)->with([
                'employee.center.scheme',
                'center',
                'scheme',
            ]);
        } elseif ($model === AdmissionTarget::class) {
            $scoped = $query->whereNull('parent_id')->whereIn('employee_id', $ids)->with([
                'employee.center.scheme',
                'center',
                'scheme',
                'weeks',
            ]);
        } else {
            $table = (new $model)->getTable();
            $scoped = $query->whereIn($table.'.id', $ids);
        }

        return app(AdminCenterContext::class)->applyToResourceQuery($scoped, $user, $model);
    }
}
