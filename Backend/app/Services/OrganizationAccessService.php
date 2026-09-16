<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Admission;
use App\Models\AdmissionTarget;
use App\Models\Attendance;
use App\Models\Center;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Scheme;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class OrganizationAccessService
{
    /**
     * @return list<int>|null null means unrestricted
     */
    public function visibleProjectIds(User $user): ?array
    {
        if ($this->hasOrganizationWideAccess($user)) {
            return null;
        }

        if ($user->isProjectHead()) {
            return Center::query()
                ->whereIn('id', $this->visibleCenterIds($user) ?? [])
                ->pluck('scheme_id')
                ->filter()
                ->unique()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        if ($user->isCenterManager()) {
            return Center::query()
                ->whereIn('id', $this->visibleCenterIds($user) ?? [])
                ->pluck('scheme_id')
                ->filter()
                ->unique()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        if ($user->isEmployeeUser() && $user->employee?->center?->scheme_id) {
            return [(int) $user->employee->center->scheme_id];
        }

        return [];
    }

    /**
     * @return list<int>|null
     */
    public function visibleCenterIds(User $user): ?array
    {
        if ($this->hasOrganizationWideAccess($user)) {
            return null;
        }

        if ($user->isProjectHead()) {
            return $user->headedCenters()->pluck('centers.id')->map(fn ($id) => (int) $id)->all();
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
        if ($this->hasOrganizationWideAccess($user)) {
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

    public function visibleSchemeIds(User $user): ?array
    {
        return $this->visibleProjectIds($user);
    }

    public function projectQuery(User $user): Builder
    {
        return $this->schemeQuery($user);
    }

    public function schemeQuery(User $user): Builder
    {
        $query = Scheme::query();
        $ids = $this->visibleSchemeIds($user);

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

    public function admissionQuery(User $user): Builder
    {
        $query = Admission::query();
        $ids = $this->visibleEmployeeIds($user);

        if ($ids === null) {
            return $query;
        }

        return $query->whereIn('employee_id', $ids);
    }

    public function leaveQuery(User $user): Builder
    {
        $query = LeaveRequest::query();
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

    public function canViewAdmission(User $user, Admission $admission): bool
    {
        $ids = $this->visibleEmployeeIds($user);

        return $ids === null || in_array((int) $admission->employee_id, $ids, true);
    }

    public function canViewLeave(User $user, LeaveRequest $leave): bool
    {
        $ids = $this->visibleEmployeeIds($user);

        return $ids === null || in_array((int) $leave->employee_id, $ids, true);
    }

    public function canApproveLeave(User $user, LeaveRequest $leave): bool
    {
        if (! $user->isCenterManager() || ! $leave->isPending()) {
            return false;
        }

        $centerIds = $this->visibleCenterIds($user) ?? [];

        return in_array((int) $leave->center_id, $centerIds, true)
            && $this->canViewLeave($user, $leave);
    }

    public function canManageSchemes(User $user): bool
    {
        return $user->isAdmin();
    }

    public function canManageDirectors(User $user): bool
    {
        return $user->isAdmin();
    }

    public function canAssignAdmissionTarget(User $user, Employee $employee): bool
    {
        if (! $user->isCenterManager()) {
            return false;
        }

        $centerIds = $this->visibleCenterIds($user) ?? [];

        return $employee->center_id !== null
            && in_array((int) $employee->center_id, $centerIds, true);
    }

    public function canViewAdmissionTarget(User $user, AdmissionTarget $target): bool
    {
        return $this->canViewEmployee($user, $target->employee ?? new Employee(['id' => $target->employee_id]));
    }

    public function admissionTargetQuery(User $user): Builder
    {
        $query = AdmissionTarget::query();
        $ids = $this->visibleEmployeeIds($user);

        if ($ids === null) {
            return $query;
        }

        return $query->whereIn('employee_id', $ids);
    }

    public function assertCanViewEmployee(User $user, Employee $employee): void
    {
        abort_unless($this->canViewEmployee($user, $employee), 403, 'You are not authorized to view this employee.');
    }

    public function assertCanViewAttendance(User $user, Attendance $attendance): void
    {
        abort_unless($this->canViewAttendance($user, $attendance), 403, 'You are not authorized to view this record.');
    }

    public function assertCanViewAdmission(User $user, Admission $admission): void
    {
        abort_unless($this->canViewAdmission($user, $admission), 403, 'You are not authorized to view this admission.');
    }

    public function assertCanViewLeave(User $user, LeaveRequest $leave): void
    {
        abort_unless($this->canViewLeave($user, $leave), 403, 'You are not authorized to view this leave request.');
    }

    public function assertCanApproveLeave(User $user, LeaveRequest $leave): void
    {
        abort_unless($this->canApproveLeave($user, $leave), 403, 'Only the assigned Center Manager can approve or reject this leave.');
    }

    public function assertCanAssignAdmissionTarget(User $user, Employee $employee): void
    {
        abort_unless(
            $this->canAssignAdmissionTarget($user, $employee),
            403,
            'Only the employee\'s assigned Center Manager can set this admission target.',
        );
    }

    public function canManageProjects(User $user): bool
    {
        return $user->isAdmin();
    }

    public function canManageProjectHeads(User $user, mixed $scope = null): bool
    {
        return $user->isAdmin() || $user->isDirector();
    }

    public function canManageCenters(User $user, Scheme|Center|null $scope = null): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isProjectHead()) {
            return false;
        }

        if ($scope === null) {
            return true;
        }

        if ($scope instanceof Center) {
            $ids = $this->visibleCenterIds($user) ?? [];

            return in_array((int) $scope->id, $ids, true);
        }

        $ids = $this->visibleSchemeIds($user) ?? [];

        return in_array((int) $scope->id, $ids, true);
    }

    public function canManageCenterManagers(User $user, ?Center $center = null): bool
    {
        if ($user->isAdmin()) {
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
        if (! $user->isCenterManager()) {
            return false;
        }

        if ($center === null) {
            return true;
        }

        $ids = $this->visibleCenterIds($user) ?? [];

        return in_array((int) $center->id, $ids, true);
    }

    public function canAccessOrgUsers(User $user): bool
    {
        return $user->isAdmin() || $user->isDirector() || $user->isProjectHead();
    }

    /**
     * @return list<string>
     */
    public function creatableOrgUserRoles(User $user): array
    {
        if ($user->isAdmin()) {
            return [
                UserRole::Director->value,
                UserRole::ProjectHead->value,
                UserRole::CenterManager->value,
            ];
        }

        if ($user->isDirector()) {
            return [UserRole::ProjectHead->value];
        }

        if ($user->isProjectHead()) {
            return [UserRole::CenterManager->value];
        }

        return [];
    }

    public function canManageOrgUser(User $actor, User $record): bool
    {
        if (! in_array($record->role, [
            UserRole::Director->value,
            UserRole::ProjectHead->value,
            UserRole::CenterManager->value,
        ], true)) {
            return false;
        }

        if ($actor->isAdmin()) {
            return true;
        }

        if ($actor->isDirector()) {
            return $record->isProjectHead() || $record->isCenterManager();
        }

        if ($actor->isProjectHead() && $record->isCenterManager()) {
            $centerIds = $this->visibleCenterIds($actor) ?? [];

            return $record->managedCenters()->whereIn('centers.id', $centerIds)->exists();
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function visibleOrgUserRoles(User $user): array
    {
        if ($user->isAdmin()) {
            return [
                UserRole::Director->value,
                UserRole::ProjectHead->value,
                UserRole::CenterManager->value,
            ];
        }

        if ($user->isDirector()) {
            return [
                UserRole::ProjectHead->value,
                UserRole::CenterManager->value,
            ];
        }

        if ($user->isProjectHead()) {
            return [UserRole::CenterManager->value];
        }

        return [];
    }

    public function supervisorRoles(): array
    {
        return [
            UserRole::Admin->value,
            UserRole::Director->value,
            UserRole::ProjectHead->value,
            UserRole::CenterManager->value,
        ];
    }

    public function hasOrganizationWideAccess(User $user): bool
    {
        return $user->isAdmin() || $user->isDirector();
    }
}
