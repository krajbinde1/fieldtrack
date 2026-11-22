<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeRoutePoint;
use App\Models\User;
use App\Support\AttendanceCalendar;
use App\Support\PublicStorage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Live status and incremental GPS feeds for the Employee Routes map.
 *
 * Positions come only from stored employee_route_points rows. Nothing in this
 * service invents a coordinate past the latest accepted fix.
 */
class LiveRouteTrackingService
{
    /**
     * A fix newer than this is "fresh". The phone captures about every 15 seconds,
     * so a few minutes of silence while still punched in means the GPS stream stopped.
     */
    public const FRESH_SECONDS = 180;

    /** Matches the mobile movement threshold (routeMovementThresholdMeters). */
    public const MOVEMENT_METERS = 25.0;

    /** About 3 km/h. Geolocator stores speed in meters per second. */
    public const MOVING_SPEED_MPS = 0.8;

    public const STATUS_MOVING = 'moving';

    public const STATUS_STOPPED = 'stopped';

    public const STATUS_SIGNAL_LOST = 'gps_signal_lost';

    public const STATUS_OFFLINE = 'offline';

    public function __construct(
        private readonly RouteDistanceCalculator $distanceCalculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(User $user): array
    {
        $this->authorize($user);

        $attendances = $this->openAttendances($user);
        $pairs = $this->latestPairs($attendances->pluck('id')->map(fn ($id) => (int) $id)->all());

        $employees = $attendances->map(function (Attendance $attendance) use ($pairs): array {
            $pair = $pairs[$attendance->id] ?? ['latest' => null, 'previous' => null];
            $status = $this->describe($attendance, $pair['latest'], $pair['previous']);

            return array_merge($this->employeePayload($attendance->employee), [
                'attendance_id' => $attendance->id,
                'punched_in' => $attendance->isRouteTrackingActive(),
                'status' => $status['status'],
                'status_label' => $status['label'],
                'speed_mps' => $status['speed_mps'],
                'speed_kmh' => $status['speed_kmh'],
                'last_gps_at' => $status['last_gps_at'],
                'latitude' => $status['latitude'],
                'longitude' => $status['longitude'],
                'heading' => $status['heading'],
                'point_id' => $status['point_id'],
                'route_url' => url('/admin/employee-routes/'.$attendance->getKey().'?mode=live'),
            ]);
        })->values()->all();

        return [
            'server_time' => $this->iso(AttendanceCalendar::now()),
            'fresh_seconds' => self::FRESH_SECONDS,
            'employees' => $employees,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function feed(User $user, Attendance $attendance, ?int $afterId, ?string $afterRecordedAt): array
    {
        $this->authorize($user, $attendance);

        $attendance->loadMissing('employee');
        [$latest, $previous] = $this->latestAcceptablePair($attendance);
        $status = $this->describe($attendance, $latest, $previous);

        $cursor = $afterId;
        $points = [];

        if ($afterId === null) {
            $points = $this->initialPoints($attendance);
            $cursor = (int) (EmployeeRoutePoint::query()->where('attendance_id', $attendance->id)->max('id') ?? 0);
        } else {
            [$points, $cursor] = $this->pointsAfter($attendance, $afterId, $afterRecordedAt);
        }

        return [
            'initial' => $afterId === null,
            'attendance_id' => $attendance->id,
            'employee' => $this->employeePayload($attendance->employee),
            'punched_in' => $attendance->isRouteTrackingActive(),
            'status' => $status['status'],
            'status_label' => $status['label'],
            'speed_mps' => $status['speed_mps'],
            'speed_kmh' => $status['speed_kmh'],
            'last_gps_at' => $status['last_gps_at'],
            'latitude' => $status['latitude'],
            'longitude' => $status['longitude'],
            'heading' => $status['heading'],
            'server_time' => $this->iso(AttendanceCalendar::now()),
            'fresh_seconds' => self::FRESH_SECONDS,
            'distance_km' => $attendance->total_route_distance_km !== null
                ? (float) $attendance->total_route_distance_km
                : null,
            'cursor_id' => $cursor > 0 ? $cursor : null,
            'points' => $points,
            'route_url' => url('/admin/employee-routes/'.$attendance->getKey().'?mode=live'),
        ];
    }

    /**
     * @return array{
     *     status: string,
     *     label: string,
     *     speed_mps: ?float,
     *     speed_kmh: ?float,
     *     last_gps_at: ?string,
     *     latitude: ?float,
     *     longitude: ?float,
     *     heading: ?float,
     *     point_id: ?int
     * }
     */
    public function describe(Attendance $attendance, ?EmployeeRoutePoint $latest, ?EmployeeRoutePoint $previous): array
    {
        if ($latest !== null && ! $this->isAcceptable($latest)) {
            $latest = null;
        }

        if ($previous !== null && ! $this->isAcceptable($previous)) {
            $previous = null;
        }

        $empty = [
            'speed_mps' => null,
            'speed_kmh' => null,
            'last_gps_at' => null,
            'latitude' => null,
            'longitude' => null,
            'heading' => null,
            'point_id' => null,
        ];

        if (! $attendance->isRouteTrackingActive()) {
            return array_merge($empty, $this->positionFrom($latest), [
                'status' => self::STATUS_OFFLINE,
                'label' => 'Offline',
            ]);
        }

        if ($latest === null) {
            return array_merge($empty, [
                'status' => self::STATUS_OFFLINE,
                'label' => 'Offline',
            ]);
        }

        $position = $this->positionFrom($latest);
        $age = abs($latest->recorded_at->getTimestamp() - AttendanceCalendar::now()->getTimestamp());

        if ($age > self::FRESH_SECONDS) {
            return array_merge($position, [
                'status' => self::STATUS_SIGNAL_LOST,
                'label' => 'GPS Signal Lost',
            ]);
        }

        $moving = $this->isMoving($latest, $previous);

        return array_merge($position, [
            'status' => $moving ? self::STATUS_MOVING : self::STATUS_STOPPED,
            'label' => $moving ? 'Moving' : 'Stopped',
        ]);
    }

    public function shouldAnimate(?EmployeeRoutePoint $previous, EmployeeRoutePoint $next): bool
    {
        if ($previous === null || ! $this->isAcceptable($previous) || ! $this->isAcceptable($next)) {
            return false;
        }

        if ($next->recorded_at->lt($previous->recorded_at)) {
            return false;
        }

        if ((int) $next->id <= (int) $previous->id && $next->recorded_at->lte($previous->recorded_at)) {
            return false;
        }

        return $this->distanceCalculator->haversineDistanceMeters(
            (float) $previous->latitude,
            (float) $previous->longitude,
            (float) $next->latitude,
            (float) $next->longitude,
        ) > RouteDistanceCalculator::DUPLICATE_DISTANCE_METERS;
    }

    public function isAcceptable(EmployeeRoutePoint $point): bool
    {
        $latitude = (float) $point->latitude;
        $longitude = (float) $point->longitude;

        if (abs($latitude) < 0.000001 && abs($longitude) < 0.000001) {
            return false;
        }

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return false;
        }

        if ($point->accuracy !== null && (float) $point->accuracy > RouteDistanceCalculator::MAX_ACCURACY_METERS) {
            return false;
        }

        return true;
    }

    private function authorize(User $user, ?Attendance $attendance = null): void
    {
        $context = app(AdminCenterContext::class);

        if ($user->isCenterCoordinator() && $context->idFor($user) === null) {
            abort(403, 'Select a center before viewing live routes.');
        }

        if ($attendance === null) {
            return;
        }

        app(OrganizationAccessService::class)->assertCanViewAttendance($user, $attendance);

        $centerId = $context->idFor($user);

        if ($centerId === null) {
            return;
        }

        $attendance->loadMissing('employee');

        abort_unless(
            (int) $attendance->employee?->center_id === $centerId,
            403,
            'You are not authorized to view this record.',
        );
    }

    /**
     * @return Collection<int, Attendance>
     */
    private function openAttendances(User $user): Collection
    {
        $query = app(OrganizationAccessService::class)
            ->attendanceQuery($user)
            ->with(['employee:id,full_name,employee_code,profile_photo_path,center_id'])
            ->whereNotNull('punch_in_time')
            ->whereNull('punch_out_time')
            ->whereDate('attendance_date', '>=', AttendanceCalendar::now()->subDay()->toDateString());

        $query = app(AdminCenterContext::class)->scopeByEmployeeCenter($query, $user);

        return $query
            ->orderByDesc('attendance_date')
            ->orderByDesc('id')
            ->get()
            ->unique('employee_id')
            ->values();
    }

    /**
     * @param  list<int>  $attendanceIds
     * @return array<int, array{latest: ?EmployeeRoutePoint, previous: ?EmployeeRoutePoint}>
     */
    private function latestPairs(array $attendanceIds): array
    {
        if ($attendanceIds === []) {
            return [];
        }

        $latestIds = $this->latestIds($attendanceIds);

        if ($latestIds === []) {
            return [];
        }

        $latestPoints = EmployeeRoutePoint::query()->whereIn('id', $latestIds)->get()->keyBy('attendance_id');
        $previousIds = $this->previousIds($attendanceIds, $latestPoints);
        $previousPoints = $previousIds === []
            ? collect()
            : EmployeeRoutePoint::query()->whereIn('id', $previousIds)->get()->keyBy('attendance_id');

        $pairs = [];

        foreach ($attendanceIds as $attendanceId) {
            $latest = $latestPoints->get($attendanceId);
            $previous = $previousPoints->get($attendanceId);

            if ($latest instanceof EmployeeRoutePoint && ! $this->isAcceptable($latest)) {
                $latest = $previous instanceof EmployeeRoutePoint && $this->isAcceptable($previous) ? $previous : null;
                $previous = null;
            }

            if ($previous instanceof EmployeeRoutePoint && ! $this->isAcceptable($previous)) {
                $previous = null;
            }

            $pairs[$attendanceId] = [
                'latest' => $latest instanceof EmployeeRoutePoint ? $latest : null,
                'previous' => $previous instanceof EmployeeRoutePoint ? $previous : null,
            ];
        }

        return $pairs;
    }

    /**
     * @param  list<int>  $attendanceIds
     * @return list<int>
     */
    private function latestIds(array $attendanceIds): array
    {
        $recorded = DB::table('employee_route_points')
            ->select('attendance_id', DB::raw('MAX(recorded_at) as recorded_at'))
            ->whereIn('attendance_id', $attendanceIds)
            ->groupBy('attendance_id');

        return DB::table('employee_route_points as points')
            ->joinSub($recorded, 'latest', function ($join): void {
                $join->on('points.attendance_id', '=', 'latest.attendance_id')
                    ->on('points.recorded_at', '=', 'latest.recorded_at');
            })
            ->groupBy('points.attendance_id')
            ->selectRaw('MAX(points.id) as id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $attendanceIds
     * @param  Collection<int, EmployeeRoutePoint>  $latestPoints
     * @return list<int>
     */
    private function previousIds(array $attendanceIds, Collection $latestPoints): array
    {
        if ($latestPoints->isEmpty()) {
            return [];
        }

        $latest = DB::query()->fromSub(
            EmployeeRoutePoint::query()
                ->whereIn('id', $latestPoints->pluck('id')->all())
                ->select(['id', 'attendance_id', 'recorded_at']),
            'latest',
        );

        $recorded = DB::table('employee_route_points as points')
            ->joinSub($latest, 'latest', function ($join): void {
                $join->on('points.attendance_id', '=', 'latest.attendance_id')
                    ->where(function ($query): void {
                        $query->whereColumn('points.recorded_at', '<', 'latest.recorded_at')
                            ->orWhere(function ($tied): void {
                                $tied->whereColumn('points.recorded_at', '=', 'latest.recorded_at')
                                    ->whereColumn('points.id', '<', 'latest.id');
                            });
                    });
            })
            ->whereIn('points.attendance_id', $attendanceIds)
            ->groupBy('points.attendance_id')
            ->selectRaw('points.attendance_id, MAX(points.recorded_at) as recorded_at');

        return DB::table('employee_route_points as points')
            ->joinSub($recorded, 'previous', function ($join): void {
                $join->on('points.attendance_id', '=', 'previous.attendance_id')
                    ->on('points.recorded_at', '=', 'previous.recorded_at');
            })
            ->groupBy('points.attendance_id')
            ->selectRaw('MAX(points.id) as id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return array{0: ?EmployeeRoutePoint, 1: ?EmployeeRoutePoint}
     */
    private function latestAcceptablePair(Attendance $attendance): array
    {
        $recent = EmployeeRoutePoint::query()
            ->where('attendance_id', $attendance->id)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->filter(fn (EmployeeRoutePoint $point): bool => $this->isAcceptable($point))
            ->values();

        return [$recent->get(0), $recent->get(1)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function initialPoints(Attendance $attendance): array
    {
        $attendance->load(['routePoints' => fn ($query) => $query->orderBy('recorded_at')->orderBy('id')]);
        $valid = $this->distanceCalculator->calculate($attendance->routePoints)['valid_points'];
        $valid = $this->decimate($valid, 1500);

        return array_map(function (array $point): array {
            $recordedAt = $point['recorded_at'] instanceof Carbon
                ? $this->iso($point['recorded_at'])
                : (string) $point['recorded_at'];

            return [
                'id' => (int) $point['id'],
                'latitude' => (float) $point['latitude'],
                'longitude' => (float) $point['longitude'],
                'accuracy' => $point['accuracy'],
                'speed' => $point['speed'],
                'heading' => $point['heading'] ?? null,
                'recorded_at' => $recordedAt,
                'animate' => false,
            ];
        }, $valid);
    }

    /**
     * @param  list<array<string, mixed>>  $points
     * @return list<array<string, mixed>>
     */
    private function decimate(array $points, int $max): array
    {
        $count = count($points);

        if ($count <= $max) {
            return $points;
        }

        $step = (int) ceil(($count - 1) / ($max - 1));
        $kept = [];

        for ($index = 0; $index < $count - 1; $index += $step) {
            $kept[] = $points[$index];
        }

        $kept[] = $points[$count - 1];

        return $kept;
    }

    /**
     * New stored fixes after the cursor. Duplicates and older timestamps are omitted.
     * The cursor still advances past them so the map does not replay or jump backward.
     *
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function pointsAfter(Attendance $attendance, int $afterId, ?string $afterRecordedAt): array
    {
        $anchor = EmployeeRoutePoint::query()
            ->where('attendance_id', $attendance->id)
            ->whereKey($afterId)
            ->first();

        $anchorTime = $anchor?->recorded_at;

        if ($afterRecordedAt !== null && $afterRecordedAt !== '') {
            try {
                $requested = Carbon::parse($afterRecordedAt);
                if ($anchorTime === null || $requested->gt($anchorTime)) {
                    $anchorTime = $requested;
                }
            } catch (\Throwable) {
                // Ignore an unparseable cursor time and keep the stored anchor.
            }
        }

        $rows = EmployeeRoutePoint::query()
            ->where('attendance_id', $attendance->id)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(300)
            ->get();

        $cursor = $rows->isEmpty() ? $afterId : (int) $rows->max('id');
        $previous = $anchor;
        $points = [];

        foreach ($rows->sortBy([['recorded_at', 'asc'], ['id', 'asc']]) as $point) {
            if (! $point instanceof EmployeeRoutePoint || ! $this->isAcceptable($point)) {
                continue;
            }

            if ($anchorTime !== null && $point->recorded_at->lt($anchorTime)) {
                continue;
            }

            $animate = $this->shouldAnimate($previous, $point);

            if (! $animate) {
                $previous = $point;

                continue;
            }

            $points[] = [
                'id' => (int) $point->id,
                'latitude' => (float) $point->latitude,
                'longitude' => (float) $point->longitude,
                'accuracy' => $point->accuracy !== null ? (float) $point->accuracy : null,
                'speed' => $point->speed !== null ? (float) $point->speed : null,
                'heading' => $point->heading !== null ? (float) $point->heading : null,
                'recorded_at' => $this->iso($point->recorded_at),
                'animate' => true,
            ];
            $previous = $point;
        }

        return [$points, $cursor];
    }

    private function isMoving(EmployeeRoutePoint $latest, ?EmployeeRoutePoint $previous): bool
    {
        $reported = $latest->speed !== null ? (float) $latest->speed : null;

        if ($reported !== null && $reported >= self::MOVING_SPEED_MPS) {
            return true;
        }

        if ($previous === null) {
            return false;
        }

        $meters = $this->distanceCalculator->haversineDistanceMeters(
            (float) $previous->latitude,
            (float) $previous->longitude,
            (float) $latest->latitude,
            (float) $latest->longitude,
        );

        return $meters > self::MOVEMENT_METERS;
    }

    /**
     * @return array{
     *     speed_mps: ?float,
     *     speed_kmh: ?float,
     *     last_gps_at: ?string,
     *     latitude: ?float,
     *     longitude: ?float,
     *     heading: ?float,
     *     point_id: ?int
     * }
     */
    private function positionFrom(?EmployeeRoutePoint $latest): array
    {
        if ($latest === null) {
            return [
                'speed_mps' => null,
                'speed_kmh' => null,
                'last_gps_at' => null,
                'latitude' => null,
                'longitude' => null,
                'heading' => null,
                'point_id' => null,
            ];
        }

        $speed = $latest->speed !== null && (float) $latest->speed >= 0
            ? round((float) $latest->speed, 2)
            : null;

        return [
            'speed_mps' => $speed,
            'speed_kmh' => $speed !== null ? round($speed * 3.6, 1) : null,
            'last_gps_at' => $this->iso($latest->recorded_at),
            'latitude' => (float) $latest->latitude,
            'longitude' => (float) $latest->longitude,
            'heading' => $latest->heading !== null ? (float) $latest->heading : null,
            'point_id' => (int) $latest->id,
        ];
    }

    /**
     * @return array{id: ?int, full_name: ?string, employee_code: ?string, photo_url: ?string}
     */
    private function employeePayload(?Employee $employee): array
    {
        return [
            'id' => $employee?->id,
            'full_name' => $employee?->full_name,
            'employee_code' => $employee?->employee_code,
            'photo_url' => PublicStorage::url($employee?->profile_photo_path),
        ];
    }

    private function iso(Carbon $value): string
    {
        return $value->timezone(AttendanceCalendar::TIMEZONE)->toIso8601String();
    }
}
