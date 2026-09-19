<?php

namespace App\Support;

use App\Models\Center;

final class CenterCodeGenerator
{
    public static function generateNext(): string
    {
        $max = Center::query()
            ->whereNotNull('code')
            ->pluck('code')
            ->reduce(function (int $carry, mixed $code): int {
                if (preg_match('/^CTR(\d+)$/i', (string) $code, $matches)) {
                    return max($carry, (int) $matches[1]);
                }

                return $carry;
            }, 0);

        do {
            $max++;
            $next = self::format($max);
        } while (Center::query()->where('code', $next)->exists());

        return $next;
    }

    public static function format(int $number): string
    {
        return 'CTR'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }
}
