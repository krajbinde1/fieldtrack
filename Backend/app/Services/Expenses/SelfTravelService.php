<?php

namespace App\Services\Expenses;

use App\Enums\ExpenseClaimStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Models\ExpenseItem;
use App\Models\ExpenseSelfTravelDay;
use App\Models\User;
use App\Services\RouteDistanceCalculator;
use App\Support\AttendanceCalendar;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class SelfTravelService
{
    public function __construct(
        private readonly RouteDistanceCalculator $distance,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function quote(User $user, string $date, ?int $exceptClaimId = null, float $additionalKm = 0): array
    {
        $employee = $user->employee;
        if (! $employee instanceof Employee) {
            return $this->unavailable($date, 'This account is not linked to an employee route.');
        }

        $rate = $employee->travel_km_rate !== null ? round((float) $employee->travel_km_rate, 2) : null;
        $limit = $employee->monthly_km_limit !== null ? round((float) $employee->monthly_km_limit, 2) : null;
        if ($rate === null || $rate <= 0 || $limit === null || $limit <= 0) {
            return $this->unavailable($date, 'Ask the Center Manager to set the travelling KM rate and monthly KM limit.');
        }

        $day = Carbon::parse($date, AttendanceCalendar::TIMEZONE)->startOfDay();
        if ($day->toDateString() > AttendanceCalendar::today()->toDateString()) {
            return $this->unavailable($date, 'Expense date cannot be in the future.');
        }

        $attendances = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $day->toDateString())
            ->with(['routePoints' => fn ($query) => $query->orderBy('recorded_at')->orderBy('id')])
            ->orderBy('id')
            ->get();

        if ($attendances->isEmpty()) {
            return $this->unavailable($date, 'No GPS route was recorded for this date.');
        }

        if ($attendances->contains(fn (Attendance $attendance): bool => blank($attendance->punch_out_time))) {
            return $this->unavailable($date, 'This route is still running. Punch out before claiming Self Travelling.');
        }

        $validPoints = [];
        $recordedKm = 0.0;
        foreach ($attendances as $attendance) {
            $calculated = $this->distance->calculate($attendance->routePoints);
            $recordedKm += (float) $calculated['total_distance_km'];
            foreach ($calculated['valid_points'] as $point) {
                $validPoints[] = $point;
            }
        }

        usort($validPoints, function (array $left, array $right): int {
            $time = $left['recorded_at'] <=> $right['recorded_at'];

            return $time !== 0 ? $time : ((int) $left['id'] <=> (int) $right['id']);
        });

        if ($validPoints === [] || $recordedKm <= 0) {
            return $this->unavailable($date, 'GPS distance is not available for this date.');
        }

        $recordedKm = round($recordedKm, 2);
        $used = $this->monthlyUsedKm($user, $day, $exceptClaimId) + $additionalKm;
        $remaining = round($limit - $used, 2);
        if ($remaining <= 0) {
            return $this->unavailable($date, 'The monthly KM limit is already used.', [
                'recorded_km' => $this->money($recordedKm),
                'km_rate' => $this->money($rate),
                'monthly_km_used' => $this->money($used),
                'monthly_km_limit' => $this->money($limit),
                'remaining_km' => '0.00',
            ]);
        }

        $eligible = round(min($recordedKm, $remaining), 2);
        $warning = $recordedKm > $remaining
            ? 'Recorded KM is above the remaining monthly allowance. Only the remaining KM can be claimed.'
            : null;
        $first = $validPoints[0];
        $last = $validPoints[array_key_last($validPoints)];

        try {
            $this->assertNoDuplicate($user, $day->toDateString(), $exceptClaimId);
        } catch (ValidationException) {
            return $this->unavailable($date, 'Self Travelling is already submitted for '.$day->toDateString().'.', [
                'recorded_km' => $this->money($recordedKm),
                'eligible_km' => $this->money($eligible),
                'km_rate' => $this->money($rate),
                'monthly_km_used' => $this->money($used),
                'monthly_km_limit' => $this->money($limit),
                'remaining_km' => $this->money($remaining),
            ]);
        }

        return [
            'available' => true,
            'message' => null,
            'warning' => $warning,
            'expense_date' => $day->toDateString(),
            'recorded_km' => $this->money($recordedKm),
            'eligible_km' => $this->money($eligible),
            'km_rate' => $this->money($rate),
            'monthly_km_used' => $this->money($used),
            'monthly_km_limit' => $this->money($limit),
            'remaining_km' => $this->money($remaining),
            'amount' => $this->money(round($eligible * $rate, 2)),
            'from' => [
                'latitude' => $first['latitude'],
                'longitude' => $first['longitude'],
            ],
            'to' => [
                'latitude' => $last['latitude'],
                'longitude' => $last['longitude'],
            ],
        ];
    }

    public function assertAvailable(User $user, string $date, ?int $exceptClaimId = null, float $additionalKm = 0): array
    {
        $quote = $this->quote($user, $date, $exceptClaimId, $additionalKm);
        if ($quote['available'] !== true) {
            throw ValidationException::withMessages([
                'items' => (string) ($quote['message'] ?? 'Self Travelling cannot be claimed for this date.'),
            ]);
        }

        return $quote;
    }

    public function assertNoDuplicate(User $user, string $date, ?int $exceptClaimId = null): void
    {
        $exists = ExpenseItem::query()
            ->where('travel_mode', 'self')
            ->whereDate('expense_date', $date)
            ->whereHas('claim', function ($query) use ($user, $exceptClaimId): void {
                $query->where('created_by_user_id', $user->id)
                    ->where('status', '!=', ExpenseClaimStatus::Rejected->value)
                    ->when($exceptClaimId !== null, fn ($query) => $query->where('id', '!=', $exceptClaimId));
            })
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'items' => 'Self Travelling is already submitted for '.$date.'.',
            ]);
        }
    }

    public function reserve(User $user, ExpenseClaim $claim): void
    {
        $dates = $claim->items()
            ->where('travel_mode', 'self')
            ->pluck('expense_date')
            ->map(fn ($date): string => Carbon::parse($date)->toDateString())
            ->unique();

        foreach ($dates as $date) {
            try {
                ExpenseSelfTravelDay::query()->create([
                    'user_id' => $user->id,
                    'travel_date' => $date,
                    'expense_claim_id' => $claim->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'items' => 'Self Travelling is already submitted for '.$date.'.',
                ]);
            }
        }
    }

    public function release(ExpenseClaim $claim): void
    {
        ExpenseSelfTravelDay::query()->where('expense_claim_id', $claim->id)->delete();
    }

    private function monthlyUsedKm(User $user, Carbon $day, ?int $exceptClaimId): float
    {
        $start = $day->copy()->startOfMonth()->toDateString();
        $end = $day->copy()->endOfMonth()->toDateString();

        return round((float) ExpenseItem::query()
            ->where('travel_mode', 'self')
            ->whereDate('expense_date', '>=', $start)
            ->whereDate('expense_date', '<=', $end)
            ->whereHas('claim', function ($query) use ($user, $exceptClaimId): void {
                $query->where('created_by_user_id', $user->id)
                    ->whereNotIn('status', [
                        ExpenseClaimStatus::Draft->value,
                        ExpenseClaimStatus::Rejected->value,
                    ])
                    ->when($exceptClaimId !== null, fn ($query) => $query->where('id', '!=', $exceptClaimId));
            })
            ->sum('eligible_km'), 2);
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, mixed>
     */
    private function unavailable(string $date, string $message, array $extra = []): array
    {
        return array_merge([
            'available' => false,
            'message' => $message,
            'warning' => null,
            'expense_date' => $date,
            'recorded_km' => null,
            'eligible_km' => null,
            'km_rate' => null,
            'monthly_km_used' => null,
            'monthly_km_limit' => null,
            'remaining_km' => null,
            'amount' => null,
            'from' => null,
            'to' => null,
        ], $extra);
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
