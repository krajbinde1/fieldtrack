<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class UserIdentity
{
    public const LOGIN_TAKEN = 'This Login ID is already taken.';

    public const NAME_MOBILE_TAKEN = 'A user with this name and mobile number already exists.';

    public const AMBIGUOUS = 'This name and mobile match more than one account. Resolve those duplicates before creating another.';

    public static function nameKey(string $name): string
    {
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?? '';

        return mb_strtolower($name);
    }

    public static function normalizeMobile(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    public static function isMobileLogin(string $value): bool
    {
        $digits = self::normalizeMobile($value);

        return $digits === trim($value) && preg_match('/^[6-9][0-9]{9}$/', $digits) === 1;
    }

    public static function userByLogin(string $loginId): ?User
    {
        $key = mb_strtolower(trim($loginId));
        if ($key === '') {
            return null;
        }

        return User::query()->whereRaw('lower(login_id) = ?', [$key])->first();
    }

    /**
     * @return Collection<int, Employee>
     */
    public static function employeeMatches(string $name, string $mobile, ?int $ignoreEmployeeId = null): Collection
    {
        if (! self::isMobileLogin($mobile)) {
            return collect();
        }

        $nameKey = self::nameKey($name);
        $digits = self::normalizeMobile($mobile);

        return Employee::query()
            ->with('user')
            ->when($ignoreEmployeeId !== null, fn ($query) => $query->whereKeyNot($ignoreEmployeeId))
            ->where('mobile', $digits)
            ->get()
            ->filter(fn (Employee $employee): bool => self::nameKey($employee->full_name) === $nameKey)
            ->values();
    }

    public static function assertAvailable(
        string $name,
        string $loginOrMobile,
        ?int $ignoreEmployeeId = null,
        ?int $ignoreUserId = null,
        bool $allowEmployeeReuse = false,
        bool $allowManagerReuse = false,
        bool $allowCoordinatorReuse = false,
        string $attribute = 'mobile',
    ): void {
        $loginUser = self::userByLogin($loginOrMobile);
        if ($loginUser !== null && $ignoreUserId !== null && $loginUser->id === $ignoreUserId) {
            $loginUser = null;
        }

        $employees = self::employeeMatches($name, $loginOrMobile, $ignoreEmployeeId);
        $nameKey = self::nameKey($name);

        if ($employees->count() > 1) {
            throw ValidationException::withMessages([$attribute => self::AMBIGUOUS]);
        }

        if ($loginUser instanceof User && self::reusable($loginUser, $nameKey, $allowEmployeeReuse, $allowManagerReuse, $allowCoordinatorReuse, $employees)) {
            return;
        }

        if ($loginUser instanceof User) {
            throw ValidationException::withMessages([
                $attribute === 'mobile' ? 'mobile' : $attribute => self::LOGIN_TAKEN,
            ]);
        }

        if ($employees->count() === 1) {
            $account = $employees->first()?->user;
            if ($allowEmployeeReuse && ($account === null || $account->isEmployeeUser())) {
                return;
            }

            throw ValidationException::withMessages([$attribute => self::NAME_MOBILE_TAKEN]);
        }
    }

    /**
     * @param  Collection<int, Employee>  $employees
     */
    private static function reusable(
        User $loginUser,
        string $nameKey,
        bool $allowEmployeeReuse,
        bool $allowManagerReuse,
        bool $allowCoordinatorReuse,
        Collection $employees,
    ): bool {
        if ($employees->count() > 1 || self::nameKey($loginUser->name) !== $nameKey) {
            return false;
        }

        if ($allowEmployeeReuse && $loginUser->isEmployeeUser()) {
            $employee = $loginUser->employee;
            if ($employee === null) {
                return false;
            }

            return $employees->isEmpty() || (int) $employees->first()?->id === (int) $employee->id;
        }

        if ($allowManagerReuse && $loginUser->role === UserRole::CenterManager->value && $loginUser->is_center_login !== true) {
            return $employees->isEmpty();
        }

        if ($allowCoordinatorReuse && $loginUser->isCenterCoordinator() && $loginUser->is_center_login !== true) {
            return $employees->isEmpty();
        }

        return false;
    }
}
