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
use Illuminate\Support\Collection;
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

    /**
     * Ordered route suggestions for the signed-in employee's attendance on this date.
     * Other employees' points and addresses are never included.
     *
     * @return list<array<string, mixed>>
     */
    public function routeLocations(User $user, string $date): array
    {
        $employee = $user->employee;
        if (! $employee instanceof Employee) {
            return [];
        }

        $day = Carbon::parse($date, AttendanceCalendar::TIMEZONE)->toDateString();
        $attendances = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $day)
            ->with(['routePoints' => function ($query) use ($employee): void {
                $query->where('employee_id', $employee->id)
                    ->orderBy('recorded_at')
                    ->orderBy('id');
            }])
            ->orderBy('id')
            ->get();

        $locations = [];
        foreach ($attendances as $attendance) {
            if (filled($attendance->punch_in_location)) {
                $locations[] = $this->locationRow(
                    label: (string) $attendance->punch_in_location,
                    address: (string) $attendance->punch_in_location,
                    latitude: $attendance->punch_in_latitude,
                    longitude: $attendance->punch_in_longitude,
                    recordedAt: $attendance->punchInAt(),
                );
            }

            foreach ($this->suggestionPoints($attendance->routePoints) as $point) {
                $locations[] = $this->locationRow(
                    id: (int) $point['id'],
                    latitude: $point['latitude'],
                    longitude: $point['longitude'],
                    recordedAt: $point['recorded_at'] ?? null,
                );
            }

            if (filled($attendance->punch_out_location)) {
                $locations[] = $this->locationRow(
                    label: (string) $attendance->punch_out_location,
                    address: (string) $attendance->punch_out_location,
                    latitude: $attendance->punch_out_latitude,
                    longitude: $attendance->punch_out_longitude,
                    recordedAt: $attendance->punchOutAt(),
                );
            }
        }

        return $locations;
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

    /**
     * Accepted GPS points in travel order. A long trace is sampled, keeping the
     * first and last points, so the dropdown stays the employee's real route.
     *
     * @param  Collection<int, mixed>  $points
     * @return list<array<string, mixed>>
     */
    private function suggestionPoints(Collection $points): array
    {
        if ($points->isEmpty()) {
            return [];
        }

        $valid = $this->distance->calculate($points)['valid_points'];
        if ($valid !== []) {
            return $this->sampleInOrder($valid, 40);
        }

        $raw = [];
        foreach ($points as $point) {
            $raw[] = [
                'id' => (int) $point->id,
                'latitude' => (float) $point->latitude,
                'longitude' => (float) $point->longitude,
                'recorded_at' => $point->recorded_at,
            ];
        }

        return $this->sampleInOrder($raw, 40);
    }

    /**
     * @param  list<array<string, mixed>>  $points
     * @return list<array<string, mixed>>
     */
    private function sampleInOrder(array $points, int $limit): array
    {
        $count = count($points);
        if ($count <= $limit || $limit < 2) {
            return $points;
        }

        $sampled = [];
        $lastIndex = $count - 1;
        for ($slot = 0; $slot < $limit; $slot++) {
            $index = (int) round($slot * $lastIndex / ($limit - 1));
            $sampled[$index] = $points[$index];
        }

        return array_values($sampled);
    }

    /**
     * @return array<string, mixed>
     */
    private function locationRow(
        ?string $label = null,
        ?string $address = null,
        mixed $latitude = null,
        mixed $longitude = null,
        mixed $recordedAt = null,
        ?int $id = null,
    ): array {
        $lat = $latitude === null || $latitude === '' ? null : round((float) $latitude, 7);
        $lng = $longitude === null || $longitude === '' ? null : round((float) $longitude, 7);
        $text = trim((string) $label);
        if ($text === '' && $lat !== null && $lng !== null) {
            $text = number_format($lat, 5, '.', '').', '.number_format($lng, 5, '.', '');
        }

        $recorded = null;
        if ($recordedAt instanceof Carbon) {
            $recorded = $recordedAt->timezone(AttendanceCalendar::TIMEZONE)->format('Y-m-d H:i:s');
        } elseif (filled($recordedAt)) {
            $recorded = Carbon::parse($recordedAt)->timezone(AttendanceCalendar::TIMEZONE)->format('Y-m-d H:i:s');
        }

        return [
            'id' => $id,
            'label' => $text === '' ? null : $text,
            'address' => filled($address) ? trim((string) $address) : null,
            'latitude' => $lat,
            'longitude' => $lng,
            'recorded_at' => $recorded,
        ];
    }
}
