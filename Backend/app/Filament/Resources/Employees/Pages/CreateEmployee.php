<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Enums\CenterStaffRole;
use App\Enums\UserRole;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\Center;
use App\Models\Employee;
use App\Models\User;
use App\Services\OrganizationAccessService;
use App\Support\LoginId;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = auth()->user();
        abort_unless($actor !== null, 403);

        $access = app(OrganizationAccessService::class);
        $centerIds = $access->visibleCenterIds($actor) ?? [];
        if (count($centerIds) === 1) {
            $data['center_id'] = $centerIds[0];
        }

        $center = Center::query()->find($data['center_id'] ?? null);
        abort_unless(
            $center !== null && $access->canManageEmployees($actor, $center),
            403,
            'You can only create users for your own Center.',
        );

        LoginId::assertUnique(LoginId::resolve($this->data['login_id'] ?? null, $data['mobile'] ?? null));

        $role = CenterStaffRole::tryFromMixed($data['staff_role'] ?? null);
        $data['staff_role'] = $role->value;
        $data['designation'] = $role->label();
        $data['department'] = $data['department'] ?? 'Center';
        $data['created_by_user_id'] = $actor->id;
        $data['status'] = (bool) ($data['status'] ?? true);

        unset($data['login_password'], $data['login_id'], $data['project_name'], $data['center_manager_name']);

        $password = $this->data['login_password'] ?? null;
        if (! filled($password)) {
            throw ValidationException::withMessages([
                'login_password' => 'Password is required.',
            ]);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Employee $employee */
        $employee = $this->record;
        $password = $this->data['login_password'];
        $loginId = LoginId::resolve($this->data['login_id'] ?? null, $employee->mobile);
        $email = $employee->email ?: $employee->mobile.'@fieldtrack.local';

        DB::transaction(function () use ($employee, $password, $loginId, $email): void {
            User::query()->create([
                'employee_id' => $employee->id,
                'name' => $employee->full_name,
                'email' => $email,
                'login_id' => $loginId,
                'password' => Hash::make($password),
                'role' => UserRole::Employee->value,
                'is_active' => (bool) $employee->status,
                'must_change_password' => true,
            ]);
        });
    }
}
