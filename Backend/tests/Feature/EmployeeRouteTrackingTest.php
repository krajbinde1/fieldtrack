<?php

use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\EmployeeRoutePoint;
use App\Models\User;
use App\Services\AdminCenterContext;
use App\Services\EmployeeRouteAnalysisService;
use App\Services\LiveRouteTrackingService;
use App\Support\AttendanceCalendar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

function trackingAttendance(int $employeeId, array $extra = []): Attendance
{
    return Attendance::query()->create(array_merge([
        'employee_id' => $employeeId,
        'attendance_date' => AttendanceCalendar::today()->toDateString(),
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
        'punch_in_latitude' => 18.5204,
        'punch_in_longitude' => 73.8567,
    ], $extra));
}

function trackingPoint(Attendance $attendance, float $latitude, float $longitude, Carbon $recordedAt, ?float $speed = null): EmployeeRoutePoint
{
    return EmployeeRoutePoint::query()->create([
        'attendance_id' => $attendance->id,
        'employee_id' => $attendance->employee_id,
        'local_uuid' => (string) Str::uuid(),
        'latitude' => $latitude,
        'longitude' => $longitude,
        'accuracy' => 12,
        'speed' => $speed,
        'heading' => 20,
        'recorded_at' => $recordedAt,
        'source' => 'test',
    ]);
}

it('moves the live feed only when a new stored gps point exists', function () {
    $org = seedOrg();
    $attendance = trackingAttendance($org['empA']->id);
    $first = trackingPoint(
        $attendance,
        18.5204300,
        73.8567000,
        AttendanceCalendar::now()->subSeconds(40),
        0,
    );

    $opened = $this->actingAs($org['centerManager'])
        ->getJson('/admin/employee-routes/tracking/'.$attendance->id)
        ->assertOk()
        ->json();

    expect($opened['initial'])->toBeTrue()
        ->and($opened['points'])->toHaveCount(1)
        ->and($opened['points'][0]['animate'])->toBeFalse()
        ->and($opened['points'][0]['id'])->toBe($first->id)
        ->and($opened['cursor_id'])->toBe($first->id);

    $again = $this->actingAs($org['centerManager'])
        ->getJson('/admin/employee-routes/tracking/'.$attendance->id.'?after_id='.$first->id)
        ->assertOk()
        ->json();

    expect($again['initial'])->toBeFalse()
        ->and($again['points'])->toBe([])
        ->and($again['cursor_id'])->toBe($first->id)
        ->and($again['latitude'])->toEqual($opened['latitude']);

    $second = trackingPoint(
        $attendance,
        18.5222300,
        73.8567000,
        AttendanceCalendar::now()->subSeconds(10),
        3.2,
    );

    $advanced = $this->actingAs($org['centerManager'])
        ->getJson('/admin/employee-routes/tracking/'.$attendance->id.'?after_id='.$first->id.'&after_recorded_at='.urlencode($opened['last_gps_at']))
        ->assertOk()
        ->json();

    expect($advanced['points'])->toHaveCount(1)
        ->and($advanced['points'][0]['id'])->toBe($second->id)
        ->and($advanced['points'][0]['animate'])->toBeTrue()
        ->and($advanced['points'][0]['latitude'])->toEqual(18.52223)
        ->and($advanced['status'])->toBe(LiveRouteTrackingService::STATUS_MOVING);
});

it('ignores duplicate and older gps points without asking the marker to move', function () {
    $org = seedOrg();
    $attendance = trackingAttendance($org['empA']->id);
    $now = AttendanceCalendar::now();
    $first = trackingPoint($attendance, 18.5204300, 73.8567000, $now->copy()->subSeconds(50), 0);
    trackingPoint($attendance, 18.5204500, 73.8567000, $now->copy()->subSeconds(20), 0);
    $older = trackingPoint($attendance, 18.5300000, 73.8567000, $now->copy()->subMinutes(30), 4);

    $payload = $this->actingAs($org['director'])
        ->getJson('/admin/employee-routes/tracking/'.$attendance->id.'?after_id='.$first->id)
        ->assertOk()
        ->json();

    $ids = collect($payload['points'])->pluck('id');

    expect($payload['points'])->toBe([])
        ->and($ids)->not->toContain($older->id)
        ->and($payload['cursor_id'])->toBeGreaterThan($first->id);
});

it('derives moving stopped signal lost and offline from stored fixes', function () {
    $org = seedOrg();
    $now = AttendanceCalendar::now();

    $moving = trackingAttendance($org['empA']->id);
    trackingPoint($moving, 18.5204300, 73.8567000, $now->copy()->subSeconds(50), 0);
    trackingPoint($moving, 18.5222300, 73.8567000, $now->copy()->subSeconds(15), null);

    $stopped = trackingAttendance($org['empB']->id);
    trackingPoint($stopped, 18.6000000, 73.9000000, $now->copy()->subSeconds(40), 0);
    trackingPoint($stopped, 18.6000200, 73.9000000, $now->copy()->subSeconds(10), 0);

    $lostAttendance = Attendance::query()->create([
        'employee_id' => $org['empA']->id,
        'attendance_date' => AttendanceCalendar::today()->subDay()->toDateString(),
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);
    trackingPoint($lostAttendance, 18.5204300, 73.8567000, $now->copy()->subMinutes(10), 1);

    $offline = trackingAttendance($org['empA']->id, [
        'attendance_date' => AttendanceCalendar::today()->subDays(3)->toDateString(),
        'punch_out_time' => '18:00:00',
    ]);
    trackingPoint($offline, 18.5204300, 73.8567000, $now->copy()->subSeconds(20), 5);

    $service = app(LiveRouteTrackingService::class);

    expect($service->feed($org['director'], $moving, null, null)['status'])->toBe('moving')
        ->and($service->feed($org['director'], $stopped, null, null)['status'])->toBe('stopped')
        ->and($service->feed($org['director'], $lostAttendance, null, null)['status'])->toBe('gps_signal_lost')
        ->and($service->feed($org['director'], $offline, null, null)['status'])->toBe('offline');
});

it('hides employees outside the caller center on the live overview', function () {
    $org = seedOrg();
    $now = AttendanceCalendar::now();
    $own = trackingAttendance($org['empA']->id);
    $other = trackingAttendance($org['empB']->id);
    trackingPoint($own, 18.5204300, 73.8567000, $now->copy()->subSeconds(20), 2);
    trackingPoint($other, 19.0000000, 74.0000000, $now->copy()->subSeconds(20), 2);

    $manager = $this->actingAs($org['centerManager'])
        ->getJson('/admin/employee-routes/tracking/overview')
        ->assertOk()
        ->json('employees');

    expect(collect($manager)->pluck('id')->all())->toBe([$org['empA']->id]);

    $this->actingAs($org['centerManager'])
        ->getJson('/admin/employee-routes/tracking/'.$other->id)
        ->assertForbidden();

    $coordinator = User::query()->create([
        'name' => 'Coordinator',
        'email' => 'coord-live@test.local',
        'login_id' => 'coordlive',
        'password' => Hash::make('Coordinator@123'),
        'role' => UserRole::CenterCoordinator->value,
        'is_active' => true,
    ]);
    $coordinator->coordinatedCenters()->attach($org['centerA']->id);

    $this->actingAs($coordinator)
        ->getJson('/admin/employee-routes/tracking/overview')
        ->assertForbidden();

    $scoped = $this->actingAs($coordinator)
        ->withSession([AdminCenterContext::SESSION_KEY => $org['centerA']->id])
        ->getJson('/admin/employee-routes/tracking/overview')
        ->assertOk()
        ->json('employees');

    expect(collect($scoped)->pluck('id')->all())->toBe([$org['empA']->id]);

    $this->actingAs($org['director'])
        ->getJson('/admin/employee-routes/tracking/overview')
        ->assertOk()
        ->assertJsonCount(2, 'employees');
});

it('keeps historical playback points and stoppages in chronological order', function () {
    $org = seedOrg();
    $attendance = trackingAttendance($org['empA']->id, [
        'punch_out_time' => '18:00:00',
    ]);
    $start = AttendanceCalendar::today()->setTime(9, 0);
    trackingPoint($attendance, 18.5204300, 73.8567000, $start->copy());
    trackingPoint($attendance, 18.5250000, 73.8567000, $start->copy()->addMinutes(3));
    trackingPoint($attendance, 18.5254000, 73.8567000, $start->copy()->addMinutes(8));
    trackingPoint($attendance, 18.5252000, 73.8569000, $start->copy()->addMinutes(16));
    trackingPoint($attendance, 18.5300000, 73.8600000, $start->copy()->addMinutes(20));

    $analysis = app(EmployeeRouteAnalysisService::class)->analyze($attendance);
    $times = collect($analysis['valid_points'])->map(fn (array $point) => $point['recorded_at']->getTimestamp())->all();
    $sorted = $times;
    sort($sorted);

    expect($times)->toBe($sorted)
        ->and($analysis['stops'])->not->toBeEmpty()
        ->and($analysis['stops'][0]['latitude'])->not->toBeNull()
        ->and($analysis['stops'][0]['start_time'])->not->toBeNull();
});

it('renders route playback and live tracking on the employee route page', function () {
    $org = seedOrg();
    $attendance = trackingAttendance($org['empA']->id);
    trackingPoint($attendance, 18.5204300, 73.8567000, AttendanceCalendar::now()->subMinutes(2), 0);
    trackingPoint($attendance, 18.5222300, 73.8567000, AttendanceCalendar::now()->subMinute(), 2);

    $this->actingAs($org['centerManager'])
        ->get('/admin/employee-routes')
        ->assertOk()
        ->assertSee('Live Tracking');

    $this->actingAs($org['centerManager'])
        ->get('/admin/employee-routes/'.$attendance->id)
        ->assertOk()
        ->assertSee('Route Playback')
        ->assertSee('Live Tracking')
        ->assertSee('Play')
        ->assertSee('Pause')
        ->assertSee('employee-route-tracking.js');

    $this->actingAs($org['centerManager'])
        ->get('/admin/employee-routes/live')
        ->assertOk()
        ->assertSee('live-employee-map');
});
