<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\OrgUsers\OrgUserResource;
use App\Models\Employee;
use App\Models\User;
use App\Services\CenterStaffCredentialService;
use App\Services\OrganizationAccessService;
use App\Support\LoginId;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    #[Url(as: 'view')]
    public string $teamLayout = 'list';

    public function getSubheading(): string|Htmlable|null
    {
        return 'Manage field users assigned to your center.';
    }

    public function setLayout(string $layout): void
    {
        if (! in_array($layout, ['list', 'hierarchy'], true)) {
            return;
        }

        $this->teamLayout = $layout;
    }

    public function content(Schema $schema): Schema
    {
        if ($this->teamLayout !== 'hierarchy') {
            return parent::content($schema);
        }

        return $schema->components([
            View::make('filament.employees.hierarchy')
                ->viewData(fn (): array => ['tree' => $this->hierarchyTree()]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('listView')
                ->label('List View')
                ->icon('heroicon-o-list-bullet')
                ->color(fn (): string => $this->teamLayout === 'list' ? 'primary' : 'gray')
                ->action(fn () => $this->setLayout('list')),
            Action::make('hierarchyView')
                ->label('Hierarchy View')
                ->icon('heroicon-o-rectangle-group')
                ->color(fn (): string => $this->teamLayout === 'hierarchy' ? 'primary' : 'gray')
                ->action(fn () => $this->setLayout('hierarchy')),
            CreateAction::make(),
        ];
    }

    public function resetHierarchyPassword(string $kind, int $id): void
    {
        if ($kind === 'employee') {
            $employee = $this->authorizedEmployee($id);
            $password = app(CenterStaffCredentialService::class)->resetPassword($employee->loadMissing('user'));
            $this->passwordNotification($password);

            return;
        }

        $manager = $this->authorizedManager($id);
        $mobile = $this->managerMobile($manager);
        if ($mobile === null) {
            Notification::make()->title('This account has no registered mobile number.')->danger()->send();

            return;
        }

        try {
            $password = LoginId::defaultPasswordFromMobile($mobile);
        } catch (ValidationException) {
            Notification::make()->title('This account has no registered mobile number.')->danger()->send();

            return;
        }

        $manager->update([
            'password' => Hash::make($password),
            'must_change_password' => true,
        ]);
        $this->passwordNotification($password);
    }

    public function deleteHierarchyUser(string $kind, int $id): void
    {
        if ($kind !== 'employee') {
            abort(403);
        }

        $employee = $this->authorizedEmployee($id);
        abort_unless(EmployeeResource::canDelete($employee), 403);

        if (! EmployeeResource::deleteManagedEmployee($employee)) {
            Notification::make()->title(Employee::DELETE_BLOCKED_MESSAGE)->danger()->send();

            return;
        }

        Notification::make()->title('User deleted.')->success()->send();
    }

    public function toggleHierarchyActive(string $kind, int $id): void
    {
        if ($kind === 'employee') {
            $employee = $this->authorizedEmployee($id);
            $active = ! (bool) $employee->status;
            $employee->update(['status' => $active]);
            $employee->user?->update(['is_active' => $active]);

            return;
        }

        $manager = $this->authorizedManager($id);
        $manager->update(['is_active' => ! (bool) $manager->is_active]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function hierarchyTree(): array
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            return [];
        }

        $access = app(OrganizationAccessService::class);
        $employees = EmployeeResource::getEloquentQuery()
            ->with(['user', 'center', 'assignedCenters'])
            ->orderBy('full_name')
            ->get();
        $employeesByCenter = [];
        foreach ($employees as $employee) {
            foreach ($employee->assignedCenterIds() as $centerId) {
                $employeesByCenter[$centerId][] = $employee;
            }
        }

        return $access->centerQuery($actor)
            ->orderBy('name')
            ->get()
            ->map(function ($center) use ($actor, $access, $employeesByCenter): array {
                $managers = $center->centerManagers()
                    ->where('users.role', UserRole::CenterManager->value)
                    ->where('users.is_center_login', false)
                    ->orderBy('users.name')
                    ->get()
                    ->map(fn (User $manager): array => $this->managerRow($actor, $access, $manager))
                    ->all();

                $people = collect($employeesByCenter[$center->id] ?? [])
                    ->map(fn (Employee $employee): array => $this->employeeRow($actor, $access, $employee))
                    ->all();

                return [
                    'id' => $center->id,
                    'name' => $center->name,
                    'managers' => $managers,
                    'employees' => $people,
                ];
            })
            ->filter(fn (array $center): bool => $center['managers'] !== [] || $center['employees'] !== [])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function managerRow(User $actor, OrganizationAccessService $access, User $manager): array
    {
        $canManage = $access->canManageOrgUser($actor, $manager);

        return [
            'kind' => 'manager',
            'id' => $manager->id,
            'name' => $manager->name,
            'meta' => 'Center Manager',
            'active' => (bool) $manager->is_active,
            'editUrl' => $canManage ? OrgUserResource::getUrl('edit', ['record' => $manager]) : null,
            'canReset' => $canManage && $this->managerMobile($manager) !== null,
            'canDelete' => false,
            'canToggle' => $canManage,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function employeeRow(User $actor, OrganizationAccessService $access, Employee $employee): array
    {
        $canManage = $access->canManageCenterUsers($actor, $employee->center)
            && ! $this->employeeAccountIsProtected($employee);

        return [
            'kind' => 'employee',
            'id' => $employee->id,
            'name' => $employee->full_name,
            'meta' => $employee->designation ?: 'Employee',
            'active' => (bool) $employee->status,
            'editUrl' => $canManage ? EmployeeResource::getUrl('edit', ['record' => $employee]) : null,
            'canReset' => $canManage && $employee->user !== null && filled($employee->mobile),
            'canDelete' => $canManage && EmployeeResource::canDelete($employee),
            'canToggle' => $canManage && ! ($employee->user?->is($actor) ?? false),
        ];
    }

    private function authorizedEmployee(int $id): Employee
    {
        $employee = EmployeeResource::getEloquentQuery()->with(['user', 'center'])->find($id);
        abort_unless($employee instanceof Employee, 404);

        $actor = auth()->user();
        abort_unless(
            $actor instanceof User
            && app(OrganizationAccessService::class)->canManageCenterUsers($actor, $employee->center)
            && ! $this->employeeAccountIsProtected($employee),
            403,
        );

        return $employee;
    }

    private function authorizedManager(int $id): User
    {
        $actor = auth()->user();
        $manager = User::query()->find($id);
        abort_unless(
            $actor instanceof User
            && $manager instanceof User
            && $manager->isCenterManager()
            && ! $manager->isCenterLogin()
            && app(OrganizationAccessService::class)->canManageOrgUser($actor, $manager),
            403,
        );

        return $manager;
    }

    private function employeeAccountIsProtected(Employee $employee): bool
    {
        $account = $employee->user;

        return $account instanceof User && $account->hasAnyRole([
            UserRole::Admin,
            UserRole::Director,
            UserRole::CenterCoordinator,
            UserRole::CenterManager,
        ]);
    }

    private function managerMobile(User $manager): ?string
    {
        $loginId = trim((string) $manager->login_id);

        return preg_match('/^[6-9][0-9]{9}$/', $loginId) === 1 ? $loginId : null;
    }

    private function passwordNotification(string $password): void
    {
        Notification::make()
            ->title(LoginId::defaultPasswordResetMessage($password))
            ->success()
            ->persistent()
            ->send();
    }
}
