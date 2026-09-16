<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CenterManagerAttendanceProfile
{
    public function employeeFor(User $user): Employee
    {
        if (! $user->isCenterManager()) {
            throw ValidationException::withMessages([
                'employee' => 'Employee profile is not linked to this account.',
            ]);
        }

        return DB::transaction(function () use ($user) {
            /** @var User $fresh */
            $fresh = User::query()->lockForUpdate()->findOrFail($user->id);
            $existing = $fresh->employee;
            if ($existing !== null) {
                return $existing;
            }

            $centerId = $fresh->managedCenters()->orderBy('centers.id')->value('centers.id');
            if ($centerId === null) {
                throw ValidationException::withMessages([
                    'employee' => 'No assigned center found for this Center Manager.',
                ]);
            }

            $name = trim((string) $fresh->name);
            if ($name === '' || str_contains($name, '@')) {
                $name = 'Center Manager';
            }

            $employee = Employee::query()->create([
                'center_id' => (int) $centerId,
                'full_name' => $name,
                'mobile' => $this->uniqueMobile((int) $fresh->id),
                'department' => 'Field',
                'designation' => 'Center Manager',
                'joining_date' => now()->toDateString(),
                'status' => true,
                'created_by_user_id' => $fresh->id,
            ]);

            $fresh->forceFill(['employee_id' => $employee->id])->save();

            return $employee;
        });
    }

    private function uniqueMobile(int $userId): string
    {
        $n = 8900000000 + $userId;
        $candidate = (string) $n;
        while (Employee::withTrashed()->where('mobile', $candidate)->exists()) {
            $n++;
            $candidate = (string) $n;
        }

        return $candidate;
    }
}
