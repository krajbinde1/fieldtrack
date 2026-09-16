<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Models\Employee;
use App\Models\User;
use App\Support\LoginId;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        LoginId::assertUnique(LoginId::resolve($this->data['login_id'] ?? null, $data['mobile'] ?? null));
        unset($data['login_password'], $data['login_id']);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Employee $employee */
        $employee = $this->record;
        $password = $this->data['login_password'] ?? null;
        $temporary = filled($password) ? $password : substr($employee->mobile, -4);
        $loginId = LoginId::resolve($this->data['login_id'] ?? null, $employee->mobile);

        DB::transaction(function () use ($employee, $temporary, $loginId): void {
            User::query()->create([
                'employee_id' => $employee->id,
                'name' => $employee->full_name,
                'email' => $employee->email ?: $employee->mobile.'@fieldtrack.local',
                'login_id' => $loginId,
                'password' => Hash::make($temporary),
                'role' => UserRole::Employee->value,
                'is_active' => (bool) $employee->status,
                'must_change_password' => true,
            ]);
        });
    }
}
