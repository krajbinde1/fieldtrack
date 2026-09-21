<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\User;
use App\Support\LoginId;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CenterStaffCredentialService
{
    public function createLoginUser(Employee $employee): User
    {
        $loginId = trim((string) $employee->mobile);
        LoginId::assertUnique($loginId, attribute: 'mobile');
        $password = LoginId::defaultPasswordFromMobile($loginId);

        return User::query()->create([
            'employee_id' => $employee->id,
            'name' => $employee->full_name,
            'email' => $employee->email ?: $employee->mobile.'@fieldtrack.local',
            'login_id' => $loginId,
            'password' => Hash::make($password),
            'role' => UserRole::Employee->value,
            'is_active' => (bool) $employee->status,
            'must_change_password' => true,
        ]);
    }

    public function resetPassword(Employee $employee): string
    {
        $user = $employee->user;
        if ($user === null) {
            throw ValidationException::withMessages([
                'password' => 'This user does not have a login account.',
            ]);
        }

        $password = LoginId::defaultPasswordFromMobile((string) $employee->mobile);
        $user->update([
            'password' => Hash::make($password),
            'must_change_password' => true,
        ]);

        return $password;
    }
}
