<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\ValidationException;

final class LoginId
{
    public static function resolve(?string $loginId, ?string $mobile): string
    {
        $loginId = trim((string) $loginId);
        if ($loginId !== '') {
            return $loginId;
        }

        $mobile = trim((string) $mobile);
        if ($mobile === '') {
            throw ValidationException::withMessages([
                'login_id' => 'Enter a Login ID or a Mobile Number to use as the Login ID.',
            ]);
        }

        return $mobile;
    }

    public static function assertUnique(string $loginId, ?int $ignoreUserId = null): void
    {
        $query = User::query()->where('login_id', $loginId);
        if ($ignoreUserId !== null) {
            $query->where('id', '!=', $ignoreUserId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'login_id' => 'This Login ID is already taken.',
            ]);
        }
    }
}
