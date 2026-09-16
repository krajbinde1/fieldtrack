<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\ValidationException;

final class LoginId
{
    public static function resolve(?string $loginId, ?string $fallback, string $emptyMessage = 'Enter a Login ID or a Mobile Number to use as the Login ID.'): string
    {
        $loginId = trim((string) $loginId);
        if ($loginId !== '') {
            return $loginId;
        }

        $fallback = trim((string) $fallback);
        if ($fallback === '') {
            throw ValidationException::withMessages([
                'login_id' => $emptyMessage,
            ]);
        }

        return $fallback;
    }

    public static function resolveFromEmail(?string $loginId, ?string $email): string
    {
        return self::resolve(
            $loginId,
            $email,
            'Enter a Login ID or an Email to use as the Login ID.',
        );
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
