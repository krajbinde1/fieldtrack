<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\User;
use App\Support\LoginId;
use App\Support\UserIdentity;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CenterStaffCredentialService
{
    public bool $reusedExistingAccount = false;

    /**
     * Creates a field user, or assigns an existing employee account to another center.
     *
     * @param  array<string, mixed>  $data
     */
    public function placeEmployee(array $data): Employee
    {
        $name = trim((string) ($data['full_name'] ?? ''));
        $mobile = UserIdentity::normalizeMobile((string) ($data['mobile'] ?? ''));
        $centerId = (int) ($data['center_id'] ?? 0);
        $this->reusedExistingAccount = false;

        UserIdentity::assertAvailable($name, $mobile, allowEmployeeReuse: true);

        $match = UserIdentity::employeeMatches($name, $mobile)->first();
        if (! $match instanceof Employee) {
            $loginUser = UserIdentity::userByLogin($mobile);
            $match = $loginUser?->employee;
        }

        if ($match instanceof Employee) {
            $this->reusedExistingAccount = true;
            $match->assignCenter($centerId);
            if ($match->user === null) {
                $this->createLoginUser($match);
            }

            return $match->load('user');
        }

        $employee = Employee::query()->create([
            'center_id' => $centerId,
            'full_name' => $name,
            'mobile' => $mobile,
            'email' => $data['email'] ?? null,
            'staff_role' => $data['staff_role'] ?? null,
            'designation' => $data['designation'] ?? null,
            'department' => $data['department'] ?? 'Center',
            'created_by_user_id' => $data['created_by_user_id'] ?? null,
            'status' => (bool) ($data['status'] ?? true),
        ]);
        $this->createLoginUser($employee);
        $employee->assignCenter($centerId);

        return $employee->load('user');
    }

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
