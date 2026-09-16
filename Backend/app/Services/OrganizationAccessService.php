<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Center;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class OrganizationAccessService
{
    /**
     * @return list<int>|null null means unrestricted
     */
    public function visibleProjectIds(User $user): ?array
    {
        if ($user->isDirector()) {
            return null;
        }

        if ($user->isProjectHead()) {
            return $user->headedProjects()->pluck('projects.id')->map(fn ($id) => (int) $id)->all();
        }

        if ($user->isCenterManager()) {
            return Center::query()
                ->whereIn('id', $this->visibleCenterIds($user) ?? [])
                ->pluck('project_id')
                ->unique()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        if ($user->isEmployeeUser() && $user->employee?->center?->project_id) {
            return [(int) $user->employee->center->project_id];
        }

        return [];
    }

    /**
     * @return list<int>|null
     */
    public function visibleCenterIds(User $user): ?array
    {
        if ($user->isDirector()) {
            return null;
        }

        if ($user->isProjectHead()) {
            $projectIds = $this->visibleProjectIds($user) ?? [];

            return Center::query()
                ->whereIn('project_id', $projectIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if ($user->isCenterManager()) {
            return $user->managedCenters()->pluck('centers.id')->map(fn ($id) => (int) $id)->all();
        }

        if ($user->isEmployeeUser() && $user->employee?->center_id) {
            return [(int) $user->employee->center_id];
        }

        return [];
    }

    /**
     * @return list<int>|null
     */
    public function visibleEmployeeIds(User $user): ?array
    {
        if ($user->isDirector()) {
            return null;
        }

        if ($user->isEmployeeUser()) {
            return $user->employee_id ? [(int) $user->employee_id] : [];
        }

        $centerIds = $this->visibleCenterIds($user) ?? [];

        return Employee::query()
            ->whereIn('center_id', $centerIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function projectQuery(User $user): Builder
    {
        $query = Project::query();
        $ids = $this->visibleProjectIds($user);

        if ($ids === null) {
            return $query;
        }

        return $query->whereIn('id', $ids);
    }

    public function centerQuery(User $user): Builder
    {
        $query = Center::query();
        $ids = $this->visibleCenterIds($user);

        if ($ids === null) {
            return $query;
        }

        return $query->whereIn('id', $ids);
    }

    public function employeeQuery(User $user): Builder
    {
        $query = Employee::query();
        $ids = $this->visibleEmployeeIds($user);

        if ($ids === null) {
            return $query;
        }

        return $query->whereIn('id', $ids);
    }

    public function attendanceQuery(User $user): Builder
    {
        $query = Attendance::query();
        $ids = $this->visibleEmployeeIds($user);

        if ($ids === null) {
            return $query;
        }

        return $query->whereIn('employee_id', $ids);
    }

    public function canViewEmployee(User $user, Employee $employee): bool
    {
        $ids = $this->visibleEmployeeIds($user);

        return $ids === null || in_array((int) $employee->id, $ids, true);
    }

    public function canViewAttendance(User $user, Attendance $attendance): bool
    {
        $ids = $this->visibleEmployeeIds($user);

        return $ids === null || in_array((int) $attendance->employee_id, $ids, true);
    }

    public function assertCanViewEmployee(User $user, Employee $employee): void
    {
        abort_unless($this->canViewEmployee($user, $employee), 403, 'You are not authorized to view this employee.');
    }

    public function assertCanViewAttendance(User $user, Attendance $attendance): void
    {
        abort_unless($this->canViewAttendance($user, $attendance), 403, 'You are not authorized to view this record.');
    }

    public function canManageProjects(User $user): bool
    {
        return $user->isDirector();
    }

    public function canManageProjectHeads(User $user): bool
    {
        return $user->isDirector();
    }

    public function canManageCenters(User $user, ?Project $project = null): bool
    {
        if ($user->isDirector()) {
            return true;
        }

        if (! $user->isProjectHead()) {
            return false;
        }

        if ($project === null) {
            return true;
        }

        $ids = $this->visibleProjectIds($user) ?? [];

        return in_array((int) $project->id, $ids, true);
    }

    public function canManageCenterManagers(User $user, ?Center $center = null): bool
    {
        if ($user->isDirector()) {
            return true;
        }

        if (! $user->isProjectHead()) {
            return false;
        }

        if ($center === null) {
            return true;
        }

        $ids = $this->visibleCenterIds($user) ?? [];

        return in_array((int) $center->id, $ids, true);
    }

    public function canManageEmployees(User $user, ?Center $center = null): bool
    {
        if ($user->isDirector()) {
            return true;
        }

        if ($user->isProjectHead()) {
            if ($center === null) {
                return true;
            }

            $ids = $this->visibleCenterIds($user) ?? [];

            return in_array((int) $center->id, $ids, true);
        }

        if (! $user->isCenterManager()) {
            return false;
        }

        if ($center === null) {
            return true;
        }

        $ids = $this->visibleCenterIds($user) ?? [];

        return in_array((int) $center->id, $ids, true);
    }

    public function supervisorRoles(): array
    {
        return [
            UserRole::Director->value,
            UserRole::ProjectHead->value,
            UserRole::CenterManager->value,
        ];
    }
}
