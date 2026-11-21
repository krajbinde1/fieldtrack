<?php

namespace App\Support;

use App\Models\Center;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class CenterLoginIdGenerator
{
    public static function fromName(string $name): string
    {
        $slug = mb_strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/u', '_', $slug) ?? '';
        $slug = trim($slug, '_');

        return $slug !== '' ? $slug : 'center';
    }

    public static function unique(string $name, ?int $ignoreCenterId = null): string
    {
        $base = self::fromName($name);
        if (! self::taken($base, $ignoreCenterId)) {
            return $base;
        }

        for ($suffix = 2; $suffix < 10000; $suffix++) {
            $candidate = $base.'_'.$suffix;
            if (! self::taken($candidate, $ignoreCenterId)) {
                return $candidate;
            }
        }

        throw ValidationException::withMessages([
            'login_id' => 'A unique Center Login ID could not be generated.',
        ]);
    }

    private static function taken(string $loginId, ?int $ignoreCenterId): bool
    {
        $centers = Center::query()->where('login_id', $loginId);
        if ($ignoreCenterId !== null) {
            $centers->whereKeyNot($ignoreCenterId);
        }

        if ($centers->exists()) {
            return true;
        }

        $users = User::query()->where('login_id', $loginId);
        if ($ignoreCenterId !== null) {
            $ownUserId = Center::query()->whereKey($ignoreCenterId)->value('login_user_id');
            if ($ownUserId !== null) {
                $users->whereKeyNot($ownUserId);
            }
        }

        return $users->exists();
    }
}
