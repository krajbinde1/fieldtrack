<?php

namespace App\Services;

use App\Enums\CenterStaffRole;
use App\Enums\UserRole;
use App\Models\AppNotification;
use App\Models\Attendance;
use App\Models\AttendancePresence;
use App\Models\Center;
use App\Models\DeviceToken;
use App\Models\Employee;
use App\Models\EmployeePresenceEvent;
use App\Models\User;
use App\Services\Notifications\FcmHttpClient;
use App\Support\AttendanceCalendar;
use Illuminate\Support\Carbon;

/**
 * Mobilizer GPS / connectivity monitor for an open punch-in.
 *
 * Confirmed GPS OFF and confirmed Internet OFF come only from device events.
 * A missing heartbeat is "Employee Offline / Connection Lost" and does not
 * mark connectivity as off.
 */
class MobilizerPresenceService
{
    public const ONLINE = 'Online / Tracking';

    public const GPS_OFF = 'GPS OFF';

    public const INTERNET_OFF = 'Internet Disconnected / Offline';

    public const GPS_SIGNAL_LOST = 'GPS Signal Lost';

    public const CONNECTION_LOST = 'Employee Offline / Connection Lost';

    public const STALE_SECONDS = 300;

    public const REMINDER_SECONDS = 900;

    /** @var list<string> */
    public const EVENT_TYPES = ['gps_off', 'gps_on', 'internet_off', 'internet_on'];

    public function __construct(
        private readonly FcmHttpClient $fcm = new FcmHttpClient,
    ) {}

    public function bootFromPunchIn(Attendance $attendance): void
    {
        $attendance->loadMissing('employee');
        if (! $this->isMobilizer($attendance->employee)) {
            return;
        }

        $now = AttendanceCalendar::now();
        $row = $this->ensurePresence($attendance);
        $hasFix = $this->validCoord($attendance->punch_in_latitude, $attendance->punch_in_longitude);
        $row->fill([
            'last_heartbeat_at' => $now,
            'last_gps_at' => $hasFix ? $now : null,
            'last_latitude' => $hasFix ? $attendance->punch_in_latitude : null,
            'last_longitude' => $hasFix ? $attendance->punch_in_longitude : null,
            'last_location_label' => $attendance->punch_in_location,
            'gps_services_enabled' => true,
            'gps_fix_valid' => $hasFix,
            'gps_state_at' => $now,
            'monitoring_stopped_at' => null,
            'alert_kind' => null,
            'condition_since' => null,
        ])->save();
    }

    public function stopMonitoring(Attendance $attendance): void
    {
        $row = AttendancePresence::query()->where('attendance_id', $attendance->id)->first();
        if ($row === null) {
            return;
        }

        $row->forceFill([
            'monitoring_stopped_at' => $row->monitoring_stopped_at ?? AttendanceCalendar::now(),
            'alert_kind' => null,
        ])->save();
    }

    /**
     * Device heartbeat and/or queued GPS and connectivity events.
     *
     * @param  array<string, mixed>  $payload
     */
    public function ingest(Attendance $attendance, array $payload): ?string
    {
        $attendance->loadMissing('employee.center');
        $events = is_array($payload['events'] ?? null) ? $payload['events'] : [];

        if (! $attendance->isRouteTrackingActive()) {
            $this->storeEvents($attendance, $events);

            return null;
        }

        $now = AttendanceCalendar::now();
        $row = $this->ensurePresence($attendance);
        if ($row->monitoring_stopped_at !== null) {
            $this->storeEvents($attendance, $events);

            return null;
        }

        $this->storeEvents($attendance, $events, $row);

        $heartbeat = ! array_key_exists('heartbeat', $payload) || (bool) $payload['heartbeat'];
        if ($heartbeat) {
            $row->last_heartbeat_at = $now;
        }

        if (array_key_exists('gps_services_enabled', $payload) && $payload['gps_services_enabled'] !== null) {
            $row->gps_services_enabled = (bool) $payload['gps_services_enabled'];
            $row->gps_state_at = $now;
            if ($row->gps_services_enabled === false) {
                $row->gps_fix_valid = false;
            }
        }

        if (array_key_exists('gps_fix_valid', $payload) && $payload['gps_fix_valid'] !== null && $row->gps_services_enabled !== false) {
            $row->gps_fix_valid = (bool) $payload['gps_fix_valid'];
        }

        $lat = $this->nullableFloat($payload['latitude'] ?? null);
        $lng = $this->nullableFloat($payload['longitude'] ?? null);
        if ($this->validCoord($lat, $lng)) {
            $row->last_latitude = $lat;
            $row->last_longitude = $lng;
            if ($row->gps_services_enabled !== false && $row->gps_fix_valid !== false) {
                $row->last_gps_at = $now;
                $row->gps_fix_valid = true;
                $row->gps_services_enabled = $row->gps_services_enabled ?? true;
            }
        }

        if (filled($payload['location_label'] ?? null)) {
            $row->last_location_label = (string) $payload['location_label'];
        }

        $row->save();

        if ($this->isMobilizer($attendance->employee)) {
            $this->reconcile($attendance, $now);
        }

        return $this->liveStatus($attendance->fresh() ?? $attendance, $now);
    }

    /**
     * A route-point upload is a heartbeat. Accepted fixes also refresh GPS.
     * Queued presence events in the same request are stored first.
     *
     * @param  list<array<string, mixed>>  $points
     * @param  array<string, mixed>|null  $presence
     */
    public function applyRouteBatch(Attendance $attendance, array $points, ?array $presence): void
    {
        $events = is_array($presence['events'] ?? null) ? $presence['events'] : [];
        $best = $this->latestValidPoint($points);
        $latestGpsOffAfterPoint = $this->gpsOffIsNewerThanPoint($events, $best);

        $payload = is_array($presence) ? $presence : [];
        $payload['events'] = $events;
        $payload['heartbeat'] = true;

        if ($best !== null && ! $latestGpsOffAfterPoint) {
            $payload['gps_services_enabled'] = true;
            $payload['gps_fix_valid'] = true;
            $payload['latitude'] = $best['latitude'];
            $payload['longitude'] = $best['longitude'];
        } elseif ($best !== null) {
            $payload['latitude'] = $payload['latitude'] ?? $best['latitude'];
            $payload['longitude'] = $payload['longitude'] ?? $best['longitude'];
        }

        $this->ingest($attendance, $payload);
    }

    public function liveStatus(Attendance $attendance, ?Carbon $now = null): ?string
    {
        $attendance->loadMissing('employee');
        if (! $attendance->isRouteTrackingActive() || ! $this->isMobilizer($attendance->employee)) {
            return null;
        }

        $now ??= AttendanceCalendar::now();
        $row = AttendancePresence::query()->where('attendance_id', $attendance->id)->first();
        if ($row?->monitoring_stopped_at !== null) {
            return null;
        }

        return $this->statusFrom($attendance, $row, $now);
    }

    public function monitorDue(?Carbon $now = null): int
    {
        $now ??= AttendanceCalendar::now();
        $checked = 0;

        Attendance::query()
            ->with('employee')
            ->whereNotNull('punch_in_time')
            ->whereNull('punch_out_time')
            ->orderBy('id')
            ->each(function (Attendance $attendance) use ($now, &$checked): void {
                if (! $this->isMobilizer($attendance->employee)) {
                    return;
                }

                $checked++;
                $this->reconcile($attendance, $now);
            });

        return $checked;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dashboardRows(User $user, ?int $centerId = null): array
    {
        if (! $user->isCenterManager()) {
            return [];
        }

        $centerIds = $this->centerIdsForManager($user);
        if ($centerId !== null) {
            $centerIds = array_values(array_filter(
                $centerIds,
                fn (int $id): bool => $id === $centerId,
            ));
        }

        if ($centerIds === []) {
            return [];
        }

        $now = AttendanceCalendar::now();

        return Attendance::query()
            ->with(['employee.center'])
            ->whereNotNull('punch_in_time')
            ->whereNull('punch_out_time')
            ->whereHas('employee', fn ($query) => $query->whereIn('center_id', $centerIds))
            ->orderByDesc('id')
            ->get()
            ->unique('employee_id')
            ->filter(function (Attendance $attendance) use ($user): bool {
                $employee = $attendance->employee;
                if (! $this->isMobilizer($employee) || $employee?->center === null) {
                    return false;
                }

                return $this->managerForCenter($employee->center)?->id === $user->id;
            })
            ->map(fn (Attendance $attendance): array => $this->present($attendance, $now))
            ->values()
            ->all();
    }

    public function reconcile(Attendance $attendance, ?Carbon $now = null): void
    {
        $attendance->refresh();
        $attendance->loadMissing('employee.center');
        if (! $this->isMobilizer($attendance->employee)) {
            return;
        }

        if (! $attendance->isRouteTrackingActive()) {
            $this->stopMonitoring($attendance);

            return;
        }

        $now ??= AttendanceCalendar::now();
        $row = $this->ensurePresence($attendance);
        if ($row->monitoring_stopped_at !== null) {
            return;
        }

        $status = $this->statusFrom($attendance, $row, $now);
        $kind = $this->kindFor($status);

        if ($kind === null) {
            if ($row->alert_kind !== null) {
                $previous = (string) $row->alert_kind;
                $episode = (int) $row->alert_episode;
                $since = $row->condition_since;
                $row->forceFill([
                    'alert_kind' => null,
                    'condition_since' => null,
                    'last_alerted_at' => $now,
                ])->save();
                $this->notify($attendance, $row->fresh() ?? $row, 'restored', $episode, 1, $now, $previous, $since);
            }

            return;
        }

        if ($row->alert_kind !== $kind) {
            $row->alert_episode = (int) $row->alert_episode + 1;
            $row->alert_sequence = 1;
            $row->alert_kind = $kind;
            $row->condition_since = $this->conditionSince($attendance, $row, $kind, $now);
            $row->last_alerted_at = $now;
            $row->save();
            $this->notify($attendance, $row, $kind, (int) $row->alert_episode, 1, $now, null, $row->condition_since);

            return;
        }

        if ($this->reminderDue($row, $now)) {
            $row->alert_sequence = (int) $row->alert_sequence + 1;
            $row->last_alerted_at = $now;
            $row->save();
            $this->notify(
                $attendance,
                $row,
                $kind,
                (int) $row->alert_episode,
                (int) $row->alert_sequence,
                $now,
                null,
                $row->condition_since,
                true,
            );
        }
    }

    public function isMobilizer(?Employee $employee): bool
    {
        return $employee !== null && $employee->staffRoleEnum() === CenterStaffRole::Mobilizer;
    }

    public function managerForCenter(Center $center): ?User
    {
        if ($center->primary_center_manager_id) {
            $primary = User::query()->find($center->primary_center_manager_id);

            return $primary instanceof User && $primary->is_active && $primary->isCenterManager()
                ? $primary
                : null;
        }

        $assigned = $center->centerManagers()
            ->where('users.role', UserRole::CenterManager->value)
            ->where('users.is_active', true)
            ->get();

        return $assigned->count() === 1 ? $assigned->first() : null;
    }

    private function ensurePresence(Attendance $attendance): AttendancePresence
    {
        $hasFix = $this->validCoord($attendance->punch_in_latitude, $attendance->punch_in_longitude);
        $punchAt = $attendance->punchInAt();

        return AttendancePresence::query()->firstOrCreate(
            ['attendance_id' => $attendance->id],
            [
                'employee_id' => $attendance->employee_id,
                'last_heartbeat_at' => $punchAt,
                'last_gps_at' => $hasFix ? $punchAt : null,
                'last_latitude' => $hasFix ? $attendance->punch_in_latitude : null,
                'last_longitude' => $hasFix ? $attendance->punch_in_longitude : null,
                'last_location_label' => $attendance->punch_in_location,
                'gps_services_enabled' => $hasFix ? true : null,
                'gps_fix_valid' => $hasFix ? true : null,
                'gps_state_at' => $hasFix ? $punchAt : null,
            ],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function storeEvents(Attendance $attendance, array $events, ?AttendancePresence $row = null): void
    {
        $parsed = [];
        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }
            $type = (string) ($event['event_type'] ?? '');
            $uuid = (string) ($event['local_uuid'] ?? '');
            if ($uuid === '' || ! in_array($type, self::EVENT_TYPES, true) || blank($event['recorded_at'] ?? null)) {
                continue;
            }
            $parsed[] = $event;
        }

        usort($parsed, function (array $left, array $right): int {
            return strcmp((string) $left['recorded_at'], (string) $right['recorded_at']);
        });

        foreach ($parsed as $event) {
            $recordedAt = Carbon::parse($event['recorded_at'])->timezone(AttendanceCalendar::TIMEZONE);
            $lat = $this->nullableFloat($event['latitude'] ?? null);
            $lng = $this->nullableFloat($event['longitude'] ?? null);
            $created = EmployeePresenceEvent::query()->firstOrCreate(
                ['local_uuid' => $event['local_uuid']],
                [
                    'attendance_id' => $attendance->id,
                    'employee_id' => $attendance->employee_id,
                    'event_type' => $event['event_type'],
                    'recorded_at' => $recordedAt,
                    'latitude' => $this->validCoord($lat, $lng) ? $lat : null,
                    'longitude' => $this->validCoord($lat, $lng) ? $lng : null,
                    'location_label' => filled($event['location_label'] ?? null) ? (string) $event['location_label'] : null,
                ],
            );

            if ($created->wasRecentlyCreated && $row !== null) {
                $this->applyEvent($row, $created);
            }
        }
    }

    private function applyEvent(AttendancePresence $row, EmployeePresenceEvent $event): void
    {
        $lat = $this->nullableFloat($event->latitude);
        $lng = $this->nullableFloat($event->longitude);
        if ($this->validCoord($lat, $lng)) {
            $row->last_latitude = $lat;
            $row->last_longitude = $lng;
            if (filled($event->location_label)) {
                $row->last_location_label = $event->location_label;
            }
        }

        if (in_array($event->event_type, ['gps_off', 'gps_on'], true)) {
            if ($row->gps_state_at === null || $event->recorded_at->greaterThanOrEqualTo($row->gps_state_at)) {
                $row->gps_services_enabled = $event->event_type === 'gps_on';
                $row->gps_state_at = $event->recorded_at;
                if ($event->event_type === 'gps_off') {
                    $row->gps_fix_valid = false;
                }
            }
        }

        if (in_array($event->event_type, ['internet_off', 'internet_on'], true)) {
            if ($row->connectivity_event_at === null || $event->recorded_at->greaterThanOrEqualTo($row->connectivity_event_at)) {
                $row->connectivity_state = $event->event_type === 'internet_off' ? 'off' : 'on';
                $row->connectivity_event_at = $event->recorded_at;
            }
        }
    }

    private function statusFrom(Attendance $attendance, ?AttendancePresence $row, Carbon $now): string
    {
        $lastContact = $this->lastContact($attendance, $row);
        $fresh = $this->isFresh($lastContact, $now);

        if ($row === null) {
            if (! $fresh) {
                return self::CONNECTION_LOST;
            }

            return $this->validCoord($attendance->punch_in_latitude, $attendance->punch_in_longitude)
                ? self::ONLINE
                : self::GPS_SIGNAL_LOST;
        }

        if ($fresh) {
            if ($row->gps_services_enabled === false) {
                return self::GPS_OFF;
            }

            $fix = $row->gps_fix_valid === true
                && $this->validCoord($row->last_latitude, $row->last_longitude)
                && $this->isFresh($row->last_gps_at, $now);

            if ($fix && $row->gps_services_enabled !== false) {
                return self::ONLINE;
            }

            return self::GPS_SIGNAL_LOST;
        }

        if ($row->connectivity_state === 'off') {
            return self::INTERNET_OFF;
        }

        return self::CONNECTION_LOST;
    }

    private function kindFor(string $status): ?string
    {
        return match ($status) {
            self::GPS_OFF => 'gps_off',
            self::GPS_SIGNAL_LOST => 'gps_signal_lost',
            self::INTERNET_OFF => 'internet_off',
            self::CONNECTION_LOST => 'connection_lost',
            default => null,
        };
    }

    private function conditionSince(Attendance $attendance, AttendancePresence $row, string $kind, Carbon $now): Carbon
    {
        return match ($kind) {
            'gps_off' => $row->gps_state_at ?? $now,
            'internet_off' => $row->connectivity_event_at ?? $this->lastContact($attendance, $row) ?? $now,
            'connection_lost' => $this->lastContact($attendance, $row) ?? $now,
            default => $now,
        };
    }

    private function lastContact(Attendance $attendance, ?AttendancePresence $row): ?Carbon
    {
        $times = array_filter([
            $row?->last_heartbeat_at,
            $row?->last_gps_at,
        ]);

        if ($times === []) {
            return $attendance->punchInAt();
        }

        return collect($times)->sortByDesc(fn (Carbon $time): int => $time->getTimestamp())->first();
    }

    private function isFresh(?Carbon $at, Carbon $now): bool
    {
        if ($at === null) {
            return false;
        }

        return ($now->getTimestamp() - $at->getTimestamp()) < self::STALE_SECONDS;
    }

    private function reminderDue(AttendancePresence $row, Carbon $now): bool
    {
        if ($row->last_alerted_at === null) {
            return true;
        }

        return ($now->getTimestamp() - $row->last_alerted_at->getTimestamp()) >= self::REMINDER_SECONDS;
    }

    private function notify(
        Attendance $attendance,
        AttendancePresence $row,
        string $kind,
        int $episode,
        int $sequence,
        Carbon $now,
        ?string $previousKind = null,
        ?Carbon $since = null,
        bool $reminder = false,
    ): void {
        $attendance->loadMissing('employee.center');
        $employee = $attendance->employee;
        $center = $employee?->center;
        if ($employee === null || $center === null) {
            return;
        }

        $manager = $this->managerForCenter($center);
        if ($manager === null) {
            return;
        }

        $status = $kind === 'restored'
            ? self::ONLINE
            : match ($kind) {
                'gps_off' => self::GPS_OFF,
                'gps_signal_lost' => self::GPS_SIGNAL_LOST,
                'internet_off' => self::INTERNET_OFF,
                default => self::CONNECTION_LOST,
            };
        $title = $this->titleFor($kind, $previousKind);
        $location = $this->locationText($row);
        $lastUpdate = $this->lastContact($attendance, $row);
        $duration = $kind === 'restored' || $status !== self::ONLINE
            ? $this->durationText($since ?? $lastUpdate, $now)
            : '—';
        $body = implode("\n", array_filter([
            $title,
            $reminder ? 'Reminder' : null,
            'Employee: '.$employee->full_name,
            'Center: '.$center->name,
            'Last location: '.$location,
            'Last update: '.($lastUpdate?->timezone(AttendanceCalendar::TIMEZONE)->format('d M Y, h:i A') ?? '—'),
            'Offline duration: '.$duration,
            'Status: '.$status,
        ]));
        $route = '/manager/route-tracking/'.$attendance->id;
        $webRoute = '/admin/employee-routes/'.$attendance->id;
        $data = [
            'type' => 'mobilizer_presence',
            'attendance_id' => (string) $attendance->id,
            'employee_id' => (string) $employee->id,
            'employee_name' => (string) $employee->full_name,
            'center_id' => (string) $center->id,
            'center_name' => (string) $center->name,
            'status' => $status,
            'last_location' => $location,
            'last_latitude' => $row->last_latitude === null ? '' : (string) $row->last_latitude,
            'last_longitude' => $row->last_longitude === null ? '' : (string) $row->last_longitude,
            'last_update_at' => $lastUpdate?->timezone(AttendanceCalendar::TIMEZONE)->toIso8601String() ?? '',
            'offline_duration' => $duration,
            'route' => $route,
            'web_route' => $webRoute,
        ];
        $key = $kind === 'restored'
            ? 'presence:'.$attendance->id.':restored:'.$episode
            : 'presence:'.$attendance->id.':'.$kind.':'.$episode.':'.$sequence;

        $notification = AppNotification::query()->firstOrCreate(
            ['dedupe_key' => $key],
            [
                'user_id' => $manager->id,
                'type' => 'mobilizer_presence',
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ],
        );

        if (! $notification->wasRecentlyCreated) {
            return;
        }

        $tokens = DeviceToken::query()->where('user_id', $manager->id)->pluck('token')->all();
        $this->fcm->sendToTokens($tokens, [
            'title' => $title,
            'body' => $employee->full_name.' · '.$center->name,
        ], $data);
    }

    private function titleFor(string $kind, ?string $previousKind): string
    {
        if ($kind === 'restored') {
            return match ($previousKind) {
                'gps_off' => 'GPS Restored',
                'internet_off' => 'Internet Restored',
                default => self::ONLINE,
            };
        }

        return match ($kind) {
            'gps_off' => self::GPS_OFF,
            'gps_signal_lost' => self::GPS_SIGNAL_LOST,
            'internet_off' => self::INTERNET_OFF,
            default => self::CONNECTION_LOST,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Attendance $attendance, Carbon $now): array
    {
        $row = AttendancePresence::query()->where('attendance_id', $attendance->id)->first();
        $status = $this->statusFrom($attendance, $row, $now);
        $employee = $attendance->employee;
        $center = $employee?->center;
        $lastUpdate = $this->lastContact($attendance, $row);
        $since = $row?->condition_since ?? ($status === self::ONLINE ? null : $lastUpdate);

        return [
            'attendance_id' => $attendance->id,
            'employee_id' => $employee?->id,
            'employee_name' => $employee?->full_name,
            'center_id' => $center?->id,
            'center_name' => $center?->name,
            'status' => $status,
            'confirmed_internet_off' => $row?->connectivity_state === 'off',
            'last_latitude' => $row?->last_latitude !== null ? (float) $row->last_latitude : ($attendance->punch_in_latitude !== null ? (float) $attendance->punch_in_latitude : null),
            'last_longitude' => $row?->last_longitude !== null ? (float) $row->last_longitude : ($attendance->punch_in_longitude !== null ? (float) $attendance->punch_in_longitude : null),
            'last_location' => $row !== null
                ? $this->locationText($row)
                : $this->coordinateText($attendance->punch_in_latitude, $attendance->punch_in_longitude, $attendance->punch_in_location),
            'last_update_at' => $lastUpdate?->timezone(AttendanceCalendar::TIMEZONE)->toIso8601String(),
            'offline_duration' => $status === self::ONLINE ? '—' : $this->durationText($since, $now),
            'route' => '/manager/route-tracking/'.$attendance->id,
            'web_route' => '/admin/employee-routes/'.$attendance->id,
            'history' => $this->history($attendance),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function history(Attendance $attendance): array
    {
        return EmployeePresenceEvent::query()
            ->where('attendance_id', $attendance->id)
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get()
            ->map(function (EmployeePresenceEvent $event): array {
                return [
                    'event_type' => $event->event_type,
                    'status' => match ($event->event_type) {
                        'gps_off' => self::GPS_OFF,
                        'internet_off' => self::INTERNET_OFF,
                        'gps_on', 'internet_on' => self::ONLINE,
                        default => null,
                    },
                    'recorded_at' => $event->recorded_at?->timezone(AttendanceCalendar::TIMEZONE)->toIso8601String(),
                    'latitude' => $event->latitude !== null ? (float) $event->latitude : null,
                    'longitude' => $event->longitude !== null ? (float) $event->longitude : null,
                    'location_label' => $event->location_label,
                ];
            })
            ->all();
    }

    /**
     * @return list<int>
     */
    private function centerIdsForManager(User $user): array
    {
        $ids = $user->managedCenters()->pluck('centers.id')->map(fn ($id): int => (int) $id)->all();
        $primary = Center::query()->where('primary_center_manager_id', $user->id)->pluck('id')->map(fn ($id): int => (int) $id)->all();

        return array_values(array_unique([...$ids, ...$primary]));
    }

    /**
     * @param  list<array<string, mixed>>  $points
     * @return array{latitude: float, longitude: float, recorded_at: string}|null
     */
    private function latestValidPoint(array $points): ?array
    {
        $best = null;
        $bestAt = null;
        foreach ($points as $point) {
            if (! is_array($point)) {
                continue;
            }
            $lat = $this->nullableFloat($point['latitude'] ?? null);
            $lng = $this->nullableFloat($point['longitude'] ?? null);
            if (! $this->validCoord($lat, $lng) || blank($point['recorded_at'] ?? null)) {
                continue;
            }
            $at = Carbon::parse($point['recorded_at']);
            if ($bestAt === null || $at->greaterThan($bestAt)) {
                $bestAt = $at;
                $best = [
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'recorded_at' => (string) $point['recorded_at'],
                ];
            }
        }

        return $best;
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @param  array{latitude: float, longitude: float, recorded_at: string}|null  $point
     */
    private function gpsOffIsNewerThanPoint(array $events, ?array $point): bool
    {
        if ($point === null) {
            return false;
        }

        $pointAt = Carbon::parse($point['recorded_at']);
        $latestOff = null;
        foreach ($events as $event) {
            if (! is_array($event) || ($event['event_type'] ?? null) !== 'gps_off' || blank($event['recorded_at'] ?? null)) {
                continue;
            }
            $at = Carbon::parse($event['recorded_at']);
            if ($latestOff === null || $at->greaterThan($latestOff)) {
                $latestOff = $at;
            }
        }

        return $latestOff !== null && $latestOff->greaterThan($pointAt);
    }

    private function locationText(AttendancePresence $row): string
    {
        return $this->coordinateText($row->last_latitude, $row->last_longitude, $row->last_location_label);
    }

    private function coordinateText(mixed $latitude, mixed $longitude, ?string $label): string
    {
        $lat = $this->nullableFloat($latitude);
        $lng = $this->nullableFloat($longitude);
        $parts = [];
        if (filled($label)) {
            $parts[] = $label;
        }
        if ($this->validCoord($lat, $lng)) {
            $parts[] = sprintf('%.5f, %.5f', $lat, $lng);
        }

        return $parts === [] ? '—' : implode(' · ', $parts);
    }

    private function durationText(?Carbon $from, Carbon $now): string
    {
        if ($from === null) {
            return '—';
        }

        $minutes = intdiv(max(0, $now->getTimestamp() - $from->getTimestamp()), 60);
        if ($minutes < 1) {
            return 'less than a minute';
        }
        if ($minutes < 60) {
            return $minutes === 1 ? '1 minute' : $minutes.' minutes';
        }

        $hours = intdiv($minutes, 60);
        $remain = $minutes % 60;
        $hourLabel = $hours === 1 ? '1 hour' : $hours.' hours';

        return $remain === 0 ? $hourLabel : $hourLabel.' '.$remain.' minutes';
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function validCoord(mixed $latitude, mixed $longitude): bool
    {
        $lat = $this->nullableFloat($latitude);
        $lng = $this->nullableFloat($longitude);
        if ($lat === null || $lng === null) {
            return false;
        }

        if (abs($lat) < 0.000001 && abs($lng) < 0.000001) {
            return false;
        }

        return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
    }
}
