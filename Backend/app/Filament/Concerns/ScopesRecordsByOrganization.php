<?php

namespace App\Filament\Concerns;

use App\Models\Attendance;
use App\Models\Center;
use App\Models\Employee;
use App\Models\Project;
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
            Project::class => $access->visibleProjectIds($user),
            Center::class => $access->visibleCenterIds($user),
            Employee::class, Attendance::class => $access->visibleEmployeeIds($user),
            default => null,
        };

        if ($ids === null) {
            return $query;
        }

        if ($model === Attendance::class) {
            return $query->whereIn('employee_id', $ids)->with(['employee.center.project']);
        }

        $table = (new $model)->getTable();

        return $query->whereIn($table.'.id', $ids);
    }
}
