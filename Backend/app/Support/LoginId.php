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

    public static function defaultPasswordFromMobile(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';
        if (strlen($digits) < 4) {
            throw ValidationException::withMessages([
                'mobile' => 'Mobile Number must have at least 4 digits to generate a password.',
            ]);
        }

        return substr($digits, -4);
    }

    public static function assertUnique(string $loginId, ?int $ignoreUserId = null, string $attribute = 'login_id'): void
    {
        $query = User::query()->where('login_id', $loginId);
        if ($ignoreUserId !== null) {
            $query->where('id', '!=', $ignoreUserId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                $attribute => 'This Login ID is already taken.',
            ]);
        }
    }
}
