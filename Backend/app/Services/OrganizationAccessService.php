<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\UserRole;
use App\Models\Admission;
use App\Models\AdmissionTarget;
use App\Models\Attendance;
use App\Models\Center;
use App\Models\Employee;
use App\Models\FieldActivity;
use App\Models\LeaveRequest;
use App\Models\Scheme;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class OrganizationAccessService
{
    /**
     * @return list<int>|null null means unrestricted
     */
    public function visibleProjectIds(User $user): ?array
    {
        if ($user->isDirector()) {
            return $this->schemeIdsForCenters($this->visibleCenterIds($user) ?? []);
        }

        if ($this->hasOrganizationWideAccess($user)) {
            return null;
        }

        if ($user->isCenterCoordinator() || $user->isCenterManager()) {
            return $this->schemeIdsForCenters($this->visibleCenterIds($user) ?? []);
        }

        if ($user->isEmployeeUser() && $user->employee) {
            return $user->employee->assignedCenterRecords()
                ->pluck('scheme_id')
                ->filter()
                ->unique()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        return [];
    }

    /**
     * @return list<int>|null
     */
    public function visibleCenterIds(User $user): ?array
    {
        if ($user->isDirector()) {
            return $user->directedCenters()->pluck('centers.id')->map(fn ($id) => (int) $id)->all();
        }

        if ($this->hasOrganizationWideAccess($user)) {
            return null;
        }

        if ($user->isCenterLogin()) {
            $centerId = Center::query()->where('login_user_id', $user->id)->value('id');

            return $centerId ? [(int) $centerId] : [];
        }

        if ($user->isCenterCoordinator()) {
            return $user->coordinatedCenters()->pluck('centers.id')->map(fn ($id) => (int) $id)->all();
        }

        if ($user->isCenterManager()) {
            return $user->managedCenters()->pluck('centers.id')->map(fn ($id) => (int) $id)->all();
        }

        if ($user->isEmployeeUser() && $user->employee) {
            return $user->employee->assignedCenterIds();
        }

        return [];
    }

    /**
     * @return list<int>|null
     */
    public function visibleEmployeeIds(User $user): ?array
    {
        if (! $user->isDirector() && $this->hasOrganizationWideAccess($user)) {
            return null;
        }

        if ($user->isEmployeeUser()) {
            return $user->employee_id ? [(int) $user->employee_id] : [];
        }

        $centerIds = $this->visibleCenterIds($user) ?? [];

        return Employee::query()
            ->where(function (Builder $query) use ($centerIds): void {
                $query->whereIn('center_id', $centerIds)
                    ->orWhereHas('assignedCenters', fn (Builder $centers) => $centers->whereIn('centers.id', $centerIds));
            })
            ->whereDoesntHave('user', function (Builder $query) {
                $query->where('role', '!=', UserRole::Employee->value);
            })
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

    public function assertCanViewCenter(User $user, int $centerId): Center
    {
        $center = Center::query()->findOrFail($centerId);

        if ($this->hasOrganizationWideAccess($user)) {
            return $center;
        }

        $ids = $this->visibleCenterIds($user);
        if ($ids === null) {
            return $center;
        }

        abort_unless(
            in_array($centerId, $ids, true),
            403,
            'You are not authorized to view this center.',
        );

        return $center;
    }

    public function requestedCenterId(Request $request): ?int
    {
        if (! $request->filled('center_id')) {
            return null;
        }

        $centerId = $request->integer('center_id');
        $this->assertCanViewCenter($request->user(), $centerId);

        return $centerId;
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

    /**
     * Active employees only. Accounts with a non-employee role are excluded.
     */
    public function fieldEmployeeQuery(User $user): Builder
    {
        return $this->employeeQuery($user)
            ->where('status', true)
            ->where(function (Builder $query): void {
                $query->whereDoesntHave('user')
                    ->orWhereHas('user', fn (Builder $account) => $account->where('role', UserRole::Employee->value));
            });
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

        if ($user->isEmployeeUser()) {
            return $user->employee_id
                ? $query->where('employee_id', (int) $user->employee_id)
                : $query->whereRaw('1 = 0');
        }

        $centerIds = $this->visibleCenterIds($user);
        if ($centerIds === null) {
            return $query;
        }

        return $query->whereIn('center_id', $centerIds);
    }

    /**
     * @return array{submitted: int, confirmed: int, draft: int, reverted: int, rejected: int, total: int}
     */
    public function admissionStatusCounts(User $user, ?int $centerId = null): array
    {
        $query = $this->admissionQuery($user);
        if ($centerId !== null) {
            $query->where('center_id', $centerId);
        }
        $counts = (clone $query)
            ->toBase()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $submitted = (int) ($counts[AdmissionStatus::Submitted->value] ?? 0);
        $confirmed = (int) ($counts[AdmissionStatus::Confirmed->value] ?? 0);
        $draft = (int) ($counts[AdmissionStatus::Draft->value] ?? 0);
        $reverted = (int) ($counts[AdmissionStatus::Reverted->value] ?? 0);
        $rejected = (int) ($counts[AdmissionStatus::Rejected->value] ?? 0);

        return [
            'submitted' => $submitted,
            'confirmed' => $confirmed,
            'draft' => $draft,
            'reverted' => $reverted,
            'rejected' => $rejected,
            'total' => (int) $query->count(),
        ];
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

    public function fieldActivityQuery(User $user): Builder
    {
        $query = FieldActivity::query();

        if ($user->isEmployeeUser()) {
            return $user->employee_id
                ? $query->where('employee_id', (int) $user->employee_id)
                : $query->whereRaw('1 = 0');
        }

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
        if ($user->isEmployeeUser()) {
            return $user->employee_id !== null
                && (int) $admission->employee_id === (int) $user->employee_id;
        }

        $centerIds = $this->visibleCenterIds($user);
        if ($centerIds === null) {
            return true;
        }

        return in_array((int) $admission->center_id, $centerIds, true);
    }

    public function canViewLeave(User $user, LeaveRequest $leave): bool
    {
        $ids = $this->visibleEmployeeIds($user);

        return $ids === null || in_array((int) $leave->employee_id, $ids, true);
    }

    public function canViewFieldActivity(User $user, FieldActivity $activity): bool
    {
        if ($user->isEmployeeUser()) {
            return $user->employee_id !== null
                && (int) $activity->employee_id === (int) $user->employee_id;
        }

        $ids = $this->visibleEmployeeIds($user);

        return $ids === null || in_array((int) $activity->employee_id, $ids, true);
    }

    public function canApproveLeave(User $user, LeaveRequest $leave): bool
    {
        if (! $leave->isPending()) {
            return false;
        }

        if ($user->isCenterManager()) {
            $centerIds = $this->visibleCenterIds($user) ?? [];

            return in_array((int) $leave->center_id, $centerIds, true)
                && $this->canViewLeave($user, $leave);
        }

        if ($user->isAdmin()) {
            return $this->isProjectHeadLeave($leave);
        }

        if ($user->isDirector()) {
            $centerIds = $this->visibleCenterIds($user) ?? [];

            return $this->isProjectHeadLeave($leave)
                && in_array((int) $leave->center_id, $centerIds, true);
        }

        return false;
    }

    public function isProjectHeadLeave(LeaveRequest $leave): bool
    {
        $leave->loadMissing(['employee.user', 'createdBy']);

        return $leave->employee?->user?->isProjectHead() === true
            || $leave->createdBy?->isProjectHead() === true;
    }

    public function constrainToProjectHeadLeaves(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->whereHas(
                'employee.user',
                fn (Builder $user) => $user->where('role', UserRole::ProjectHead->value),
            )->orWhereHas(
                'createdBy',
                fn (Builder $user) => $user->where('role', UserRole::ProjectHead->value),
            );
        });
    }

    public function projectHeadLeaveQuery(User $user): Builder
    {
        return $this->constrainToProjectHeadLeaves($this->leaveQuery($user));
    }

    public function canReviewAdmission(User $user, Admission $admission): bool
    {
        if (! $admission->isSubmitted() || ! $this->canViewAdmission($user, $admission)) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isDirector()) {
            $centerIds = $this->visibleCenterIds($user) ?? [];

            return in_array((int) $admission->center_id, $centerIds, true);
        }

        if (! $user->isCenterManager()) {
            return false;
        }

        $centerIds = $this->visibleCenterIds($user) ?? [];

        return in_array((int) $admission->center_id, $centerIds, true);
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

    public function assertCanViewAdmissionTarget(User $user, AdmissionTarget $target): void
    {
        abort_unless($this->canViewAdmissionTarget($user, $target), 403, 'You are not authorized to view this admission target.');
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

    public function assertCanViewFieldActivity(User $user, FieldActivity $activity): void
    {
        abort_unless(
            $this->canViewFieldActivity($user, $activity),
            403,
            'You are not authorized to view this field activity.',
        );
    }

    public function assertCanApproveLeave(User $user, LeaveRequest $leave): void
    {
        abort_unless(
            $this->canApproveLeave($user, $leave),
            403,
            $user->isAdminOrDirector()
                ? 'Directors can only approve or reject Project Manager leave requests.'
                : 'Only the assigned Center Manager can approve or reject this leave.',
        );
    }

    public function assertCanReviewAdmission(User $user, Admission $admission): void
    {
        abort_unless(
            $this->canReviewAdmission($user, $admission),
            403,
            'Only an assigned Center Manager or an organization admin can review this admission.',
        );
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

        if (! $user->isDirector()) {
            return false;
        }

        if ($scope instanceof Center) {
            return in_array((int) $scope->id, $this->visibleCenterIds($user) ?? [], true);
        }

        if ($scope instanceof Scheme) {
            return in_array((int) $scope->id, $this->visibleProjectIds($user) ?? [], true);
        }

        return true;
    }

    public function canManageCenterManagers(User $user, ?Center $center = null): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isDirector() && ! $user->isCenterCoordinator()) {
            return false;
        }

        $ids = $this->visibleCenterIds($user) ?? [];
        if ($center === null) {
            return $ids !== [];
        }

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

    public function canManageCenterUsers(User $user, ?Center $center = null): bool
    {
        if ($this->canManageEmployees($user, $center)) {
            return true;
        }

        if (! $user->isCenterCoordinator()) {
            return false;
        }

        $ids = $this->visibleCenterIds($user) ?? [];

        if ($center === null) {
            return $ids !== [];
        }

        return in_array((int) $center->id, $ids, true);
    }

    public function canDeleteManagedEmployee(User $actor, Employee $employee): bool
    {
        $center = $employee->center;

        if (! $center instanceof Center || ! $this->canManageCenterUsers($actor, $center)) {
            return false;
        }

        if ($actor->employee_id !== null && (int) $actor->employee_id === (int) $employee->getKey()) {
            return false;
        }

        $account = $employee->user;

        if (! $account instanceof User) {
            return true;
        }

        if ($account->is($actor)) {
            return false;
        }

        return ! $account->hasAnyRole([
            UserRole::Admin,
            UserRole::Director,
            UserRole::CenterCoordinator,
            UserRole::CenterManager,
        ]);
    }

    public function canAccessOrgUsers(User $user): bool
    {
        return $user->isAdmin() || $user->isDirector() || $user->isCenterCoordinator();
    }

    public function isReadOnlyCenterViewer(User $user): bool
    {
        return $user->isCenterCoordinator();
    }

    /**
     * @return list<string>
     */
    public function creatableOrgUserRoles(User $user): array
    {
        if ($user->isAdmin()) {
            return [
                UserRole::Director->value,
                UserRole::CenterCoordinator->value,
                UserRole::CenterManager->value,
            ];
        }

        if ($user->isDirector()) {
            return [
                UserRole::CenterCoordinator->value,
                UserRole::CenterManager->value,
            ];
        }

        if ($user->isCenterCoordinator()) {
            return [UserRole::CenterManager->value];
        }

        return [];
    }

    /**
     * Roles an actor may save on this account. Legacy Project Manager records
     * can keep that stored role, which no longer grants login or permissions.
     *
     * @return list<string>
     */
    public function assignableOrgUserRoles(User $actor, User $record): array
    {
        $roles = $this->creatableOrgUserRoles($actor);

        if ($record->role === UserRole::ProjectHead->value) {
            $roles[] = UserRole::ProjectHead->value;
        }

        return array_values(array_unique($roles));
    }

    public function canChangeOrgUserRole(User $actor, User $record): bool
    {
        return $actor->isAdminOrDirector() && $this->canManageOrgUser($actor, $record);
    }

    public function canDeleteOrgUser(User $actor, User $record): bool
    {
        if ($record->is($actor) || $record->isCenterLogin()) {
            return false;
        }

        if ($actor->isAdmin()) {
            if ($record->isAdmin()) {
                return User::query()->where('role', UserRole::Admin->value)->count() > 1;
            }

            return true;
        }

        return $actor->isDirector()
            && ! $record->isAdmin()
            && $this->canManageOrgUser($actor, $record);
    }

    public function canManageOrgUser(User $actor, User $record): bool
    {
        if ($record->isCenterLogin()) {
            return false;
        }

        if (! in_array($record->role, [
            UserRole::Director->value,
            UserRole::CenterCoordinator->value,
            UserRole::ProjectHead->value,
            UserRole::CenterManager->value,
        ], true)) {
            return false;
        }

        if ($actor->isAdmin()) {
            return true;
        }

        if ($actor->isDirector()) {
            if (! ($record->isCenterCoordinator() || $record->isProjectHead() || $record->isCenterManager())) {
                return false;
            }

            $centerIds = $this->visibleCenterIds($actor) ?? [];

            return $centerIds !== []
                && (
                    $record->coordinatedCenters()->whereIn('centers.id', $centerIds)->exists()
                    || $record->headedCenters()->whereIn('centers.id', $centerIds)->exists()
                    || $record->managedCenters()->whereIn('centers.id', $centerIds)->exists()
                );
        }

        if ($actor->isCenterCoordinator() && $record->isCenterManager()) {
            $centerIds = $this->visibleCenterIds($actor) ?? [];
            if ($centerIds === []) {
                return false;
            }

            $managed = $record->managedCenters()
                ->pluck('centers.id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            return $managed !== [] && array_diff($managed, $centerIds) === [];
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
                UserRole::CenterCoordinator->value,
                UserRole::ProjectHead->value,
                UserRole::CenterManager->value,
            ];
        }

        if ($user->isDirector()) {
            return [
                UserRole::CenterCoordinator->value,
                UserRole::ProjectHead->value,
                UserRole::CenterManager->value,
            ];
        }

        if ($user->isCenterCoordinator()) {
            return [UserRole::CenterManager->value];
        }

        return [];
    }

    public function supervisorRoles(): array
    {
        return [
            UserRole::Admin->value,
            UserRole::Director->value,
            UserRole::CenterCoordinator->value,
            UserRole::CenterManager->value,
        ];
    }

    public function hasOrganizationWideAccess(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * @param  list<int>  $centerIds
     * @return list<int>
     */
    private function schemeIdsForCenters(array $centerIds): array
    {
        return Center::query()
            ->whereIn('id', $centerIds === [] ? [-1] : $centerIds)
            ->pluck('scheme_id')
            ->filter()
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
