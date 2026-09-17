<?php

namespace App\Services;

use App\Enums\FieldActivityType;
use App\Models\FieldActivity;
use App\Models\User;
use App\Support\AttendanceCalendar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

final class FieldActivityService
{
    public function __construct(
        private readonly OrganizationAccessService $access,
    ) {}

    /**
     * @return list<string>
     */
    public function relations(): array
    {
        return ['employee', 'center', 'scheme'];
    }

    public function createForEmployee(User $user, array $data, UploadedFile $photo): FieldActivity
    {
        abort_unless(
            $user->isEmployeeUser() && $user->employee_id,
            403,
            'Only field employees can submit field activities.',
        );

        $employee = $user->employee;
        abort_unless($employee, 403, 'Employee profile is not linked to this account.');
        $employee->loadMissing('center');

        $type = FieldActivityType::tryFromMixed($data['activity_type'] ?? null);
        $name = trim((string) ($data['activity_name'] ?? ''));
        if ($name === '') {
            $name = $type->label();
        }

        $activityAt = filled($data['activity_at'] ?? null)
            ? Carbon::parse($data['activity_at'])->timezone(AttendanceCalendar::TIMEZONE)
            : AttendanceCalendar::now();

        $path = str_replace('\\', '/', $photo->store('field-activities', 'public'));

        $activity = FieldActivity::query()->create([
            'employee_id' => $employee->id,
            'user_id' => $user->id,
            'center_id' => $employee->center_id,
            'scheme_id' => $employee->center?->scheme_id,
            'activity_type' => $type,
            'activity_name' => $name,
            'activity_at' => $activityAt,
            'remarks' => filled($data['remarks'] ?? null) ? trim((string) $data['remarks']) : null,
            'photo_path' => $path,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'location' => trim((string) $data['location']),
        ]);

        return $activity->load($this->relations());
    }

    public function applyFilters(Builder $query, Request $request, User $user): Builder
    {
        $centerId = $this->access->requestedCenterId($request);
        if ($centerId !== null) {
            $query->where('center_id', $centerId);
        }

        if ($request->filled('employee_id')) {
            $employeeId = $request->integer('employee_id');
            $employee = $this->access->employeeQuery($user)->whereKey($employeeId)->first();
            abort_unless($employee, 403, 'You are not authorized to view this employee.');
            $query->where('employee_id', $employeeId);
        }

        $period = $request->string('period')->toString();
        if ($period === '' && ! $request->filled('date_from') && ! $request->filled('date_to')) {
            $period = 'today';
        }

        $today = AttendanceCalendar::today();

        return match ($period) {
            'this_week' => $query->whereBetween('activity_at', [
                $today->copy()->startOfWeek(Carbon::MONDAY)->startOfDay(),
                $today->copy()->endOfDay(),
            ]),
            'this_month' => $query->whereBetween('activity_at', [
                $today->copy()->startOfMonth()->startOfDay(),
                $today->copy()->endOfDay(),
            ]),
            'custom', '' => $this->applyCustomDates($query, $request),
            'all' => $query,
            default => $query->whereDate('activity_at', $today->toDateString()),
        };
    }

    private function applyCustomDates(Builder $query, Request $request): Builder
    {
        if ($request->filled('date_from')) {
            $query->whereDate('activity_at', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('activity_at', '<=', $request->date('date_to'));
        }

        return $query;
    }
}
