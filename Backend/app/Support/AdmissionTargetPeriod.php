<?php

namespace App\Support;

use App\Support\AttendanceCalendar;
use Illuminate\Support\Carbon;

final class AdmissionTargetPeriod
{
    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function resolve(string $preset, ?string $from = null, ?string $to = null): array
    {
        $today = Carbon::now(AttendanceCalendar::TIMEZONE)->startOfDay();

        return match ($preset) {
            'today' => [$today->copy(), $today->copy()],
            'this_week', 'week' => [
                $today->copy()->startOfWeek(Carbon::MONDAY),
                $today->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay(),
            ],
            'last_week' => [
                $today->copy()->subWeek()->startOfWeek(Carbon::MONDAY),
                $today->copy()->subWeek()->endOfWeek(Carbon::SUNDAY)->startOfDay(),
            ],
            'this_month', 'month' => [
                $today->copy()->startOfMonth(),
                $today->copy()->endOfMonth()->startOfDay(),
            ],
            'last_month' => [
                $today->copy()->subMonthNoOverflow()->startOfMonth(),
                $today->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay(),
            ],
            'custom' => [
                Carbon::parse((string) $from, AttendanceCalendar::TIMEZONE)->startOfDay(),
                Carbon::parse((string) $to, AttendanceCalendar::TIMEZONE)->startOfDay(),
            ],
            default => [
                $today->copy()->startOfMonth(),
                $today->copy()->endOfMonth()->startOfDay(),
            ],
        };
    }

    public static function calendarWeek(Carbon $date): array
    {
        $day = $date->copy()->timezone(AttendanceCalendar::TIMEZONE)->startOfDay();

        return [
            $day->copy()->startOfWeek(Carbon::MONDAY),
            $day->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay(),
        ];
    }

    public static function calendarMonth(Carbon $date): array
    {
        $day = $date->copy()->timezone(AttendanceCalendar::TIMEZONE)->startOfDay();

        return [
            $day->copy()->startOfMonth(),
            $day->copy()->endOfMonth()->startOfDay(),
        ];
    }

    /**
     * Calendar weeks overlapping a month, clipped to month boundaries.
     *
     * @return list<array{start: Carbon, end: Carbon, days: int}>
     */
    public static function weeksInMonth(Carbon $monthStart): array
    {
        $monthStart = $monthStart->copy()->timezone(AttendanceCalendar::TIMEZONE)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth()->startOfDay();
        $cursor = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $weeks = [];

        while ($cursor->lte($monthEnd)) {
            $weekEnd = $cursor->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay();
            $segStart = $cursor->copy()->max($monthStart);
            $segEnd = $weekEnd->copy()->min($monthEnd);

            if ($segStart->lte($segEnd)) {
                $weeks[] = [
                    'start' => $segStart->copy(),
                    'end' => $segEnd->copy(),
                    'days' => (int) $segStart->diffInDays($segEnd) + 1,
                ];
            }

            $cursor->addWeek();
        }

        return $weeks;
    }

    /**
     * Largest-remainder split so weekly counts sum exactly to $total.
     *
     * @param  list<array{start: Carbon, end: Carbon, days: int}>  $weeks
     * @return list<array{start: Carbon, end: Carbon, days: int, count: int}>
     */
    public static function allocateExactly(int $total, array $weeks): array
    {
        $totalDays = max(1, (int) array_sum(array_column($weeks, 'days')));
        $parts = [];
        $allocated = 0;

        foreach ($weeks as $index => $week) {
            $raw = $total * ($week['days'] / $totalDays);
            $floor = (int) floor($raw);
            $parts[] = [
                'start' => $week['start'],
                'end' => $week['end'],
                'days' => $week['days'],
                'count' => $floor,
                'frac' => $raw - $floor,
                'index' => $index,
            ];
            $allocated += $floor;
        }

        $remainder = $total - $allocated;
        usort($parts, function (array $a, array $b): int {
            return $b['frac'] <=> $a['frac']
                ?: $b['days'] <=> $a['days']
                ?: $a['index'] <=> $b['index'];
        });

        for ($i = 0; $i < $remainder; $i++) {
            $parts[$i]['count']++;
        }

        usort($parts, fn (array $a, array $b): int => $a['index'] <=> $b['index']);

        return array_map(static function (array $part): array {
            unset($part['frac'], $part['index']);

            return $part;
        }, $parts);
    }

    public static function overlapDays(Carbon $aStart, Carbon $aEnd, Carbon $bStart, Carbon $bEnd): int
    {
        $start = $aStart->copy()->max($bStart);
        $end = $aEnd->copy()->min($bEnd);

        if ($start->gt($end)) {
            return 0;
        }

        return (int) $start->diffInDays($end) + 1;
    }
}
