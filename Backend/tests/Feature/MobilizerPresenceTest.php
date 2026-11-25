<?php

use App\Enums\UserRole;
use App\Filament\Widgets\CenterManagerDashboardWidget;
use App\Models\AppNotification;
use App\Models\Attendance;
use App\Models\AttendancePresence;
use App\Models\Employee;
use App\Models\EmployeePresenceEvent;
use App\Models\User;
use App\Services\MobilizerPresenceService;
use App\Support\AttendanceCalendar;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

afterEach(function () {
    Carbon::setTestNow();
});

function presenceOrg(): array
{
    $org = seedOrg();
    $org['centerA']->update(['primary_center_manager_id' => $org['centerManager']->id]);

    $otherManager = User::create([
        'name' => 'Other CM',
        'email' => 'other-cm@test.local',
        'login_id' => 'othercm',
        'password' => Hash::make('CenterMgr@123'),
        'role' => UserRole::CenterManager->value,
        'is_active' => true,
    ]);
    $otherManager->managedCenters()->attach($org['centerB']->id);
    $org['centerB']->update(['primary_center_manager_id' => $otherManager->id]);

    $coordinator = User::create([
        'name' => 'Coordinator',
        'email' => 'coord-presence@test.local',
        'login_id' => 'coordpresence',
        'password' => Hash::make('Coordinator@123'),
        'role' => UserRole::CenterCoordinator->value,
        'is_active' => true,
    ]);
    $coordinator->coordinatedCenters()->attach($org['centerA']->id);

    $trainer = Employee::create([
        'center_id' => $org['centerA']->id,
        'full_name' => 'Trainer A',
        'mobile' => '9000000099',
        'status' => true,
        'staff_role' => 'trainer',
    ]);

    $org['otherManager'] = $otherManager;
    $org['coordinator'] = $coordinator;
    $org['trainer'] = $trainer;

    return $org;
}

function openMobilizerShift(Employee $employee, Carbon $at): Attendance
{
    Carbon::setTestNow($at);
    $attendance = Attendance::query()->create([
        'employee_id' => $employee->id,
        'attendance_date' => AttendanceCalendar::today()->toDateString(),
        'punch_in_time' => $at->format('H:i:s'),
        'punch_in_latitude' => 18.5204,
        'punch_in_longitude' => 73.8567,
        'punch_in_location' => 'Gate',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);
    app(MobilizerPresenceService::class)->bootFromPunchIn($attendance->fresh());

    return $attendance->fresh();
}

function presenceCountFor(int $userId): int
{
    return AppNotification::query()->where('user_id', $userId)->where('type', 'mobilizer_presence')->count();
}

it('treats punch in and a fresh heartbeat as Online / Tracking', function () {
    Storage::fake('public');
    $org = presenceOrg();
    $start = Carbon::parse('2026-11-25 10:00:00', 'Asia/Kolkata');
    Carbon::setTestNow($start);

    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/attendance/punch-in', [
            'latitude' => 18.5204,
            'longitude' => 73.8567,
            'location_address' => 'Gate',
            'photo' => UploadedFile::fake()->image('in.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    $attendance = Attendance::query()->where('employee_id', $org['empA']->id)->firstOrFail();
    $service = app(MobilizerPresenceService::class);

    expect($service->liveStatus($attendance))->toBe(MobilizerPresenceService::ONLINE)
        ->and(presenceCountFor($org['centerManager']->id))->toBe(0);

    $this->actingAs($org['userA'], 'sanctum')
        ->postJson('/api/employee/presence/heartbeat', [
            'attendance_id' => $attendance->id,
            'gps_services_enabled' => true,
            'gps_fix_valid' => true,
            'latitude' => 18.521,
            'longitude' => 73.857,
        ])
        ->assertOk()
        ->assertJsonPath('status', MobilizerPresenceService::ONLINE);

    expect($service->liveStatus($attendance->fresh()))->toBe(MobilizerPresenceService::ONLINE)
        ->and(presenceCountFor($org['centerManager']->id))->toBe(0);
});

it('alerts only the assigned center manager for a confirmed GPS OFF event', function () {
    $org = presenceOrg();
    $start = Carbon::parse('2026-11-25 10:00:00', 'Asia/Kolkata');
    $attendance = openMobilizerShift($org['empA'], $start);
    Carbon::setTestNow($start->copy()->addMinute());

    $this->actingAs($org['userA'], 'sanctum')
        ->postJson('/api/employee/presence/heartbeat', [
            'attendance_id' => $attendance->id,
            'gps_services_enabled' => false,
            'gps_fix_valid' => false,
            'latitude' => 18.5204,
            'longitude' => 73.8567,
            'location_label' => 'Gate',
            'events' => [[
                'local_uuid' => 'gps-off-1',
                'event_type' => 'gps_off',
                'recorded_at' => $start->copy()->addMinute()->toIso8601String(),
                'latitude' => 18.5204,
                'longitude' => 73.8567,
                'location_label' => 'Gate',
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('status', MobilizerPresenceService::GPS_OFF)
        ->assertJsonPath('confirmed_internet_off', false);

    $note = AppNotification::query()->where('user_id', $org['centerManager']->id)->first();

    expect($note)->not->toBeNull()
        ->and($note->title)->toBe('GPS OFF')
        ->and($note->body)->toContain('Emp A')
        ->and($note->body)->toContain('Center A')
        ->and($note->body)->toContain('18.52040, 73.85670')
        ->and($note->body)->toContain('Last update:')
        ->and($note->body)->toContain('Offline duration:')
        ->and($note->data['route'])->toBe('/manager/route-tracking/'.$attendance->id)
        ->and($note->data['web_route'])->toBe('/admin/employee-routes/'.$attendance->id)
        ->and(AttendancePresence::query()->where('attendance_id', $attendance->id)->value('connectivity_state'))->toBeNull()
        ->and(presenceCountFor($org['otherManager']->id))->toBe(0)
        ->and(presenceCountFor($org['coordinator']->id))->toBe(0)
        ->and(presenceCountFor($org['director']->id))->toBe(0)
        ->and(presenceCountFor($org['admin']->id))->toBe(0);

    $rows = $this->actingAs($org['centerManager'], 'sanctum')
        ->getJson('/api/dashboard')
        ->assertOk()
        ->json('data.mobilizer_presence');

    expect(collect($rows)->pluck('status')->all())->toBe([MobilizerPresenceService::GPS_OFF])
        ->and(collect($rows)->pluck('employee_name')->all())->toBe(['Emp A']);

    expect($this->actingAs($org['director'], 'sanctum')->getJson('/api/dashboard')->json('data'))
        ->not->toHaveKey('mobilizer_presence');
    expect($this->actingAs($org['coordinator'], 'sanctum')->getJson('/api/dashboard')->json('data'))
        ->not->toHaveKey('mobilizer_presence');

    $otherRows = $this->actingAs($org['otherManager'], 'sanctum')
        ->getJson('/api/dashboard')
        ->json('data.mobilizer_presence');
    expect(collect($otherRows)->pluck('employee_id'))->not->toContain($org['empA']->id);

    Livewire::actingAs($org['centerManager'])
        ->test(CenterManagerDashboardWidget::class)
        ->assertSee('Mobilizer tracking')
        ->assertSee('GPS OFF')
        ->assertSee('Emp A')
        ->assertDontSee('Emp B');

    Livewire::actingAs($org['otherManager'])
        ->test(CenterManagerDashboardWidget::class)
        ->assertDontSee('GPS OFF')
        ->assertDontSee('Emp A');
});

it('notifies connection lost after five minutes without marking internet off, and reminds after fifteen', function () {
    $org = presenceOrg();
    $start = Carbon::parse('2026-11-25 10:00:00', 'Asia/Kolkata');
    $attendance = openMobilizerShift($org['empA'], $start);

    Carbon::setTestNow($start->copy()->addMinutes(4));
    $this->artisan('presence:monitor')->assertSuccessful();
    expect(presenceCountFor($org['centerManager']->id))->toBe(0)
        ->and(app(MobilizerPresenceService::class)->liveStatus($attendance->fresh()))->toBe(MobilizerPresenceService::ONLINE);

    Carbon::setTestNow($start->copy()->addMinutes(5));
    $this->artisan('presence:monitor')->assertSuccessful();

    $row = AttendancePresence::query()->where('attendance_id', $attendance->id)->first();
    $note = AppNotification::query()->where('user_id', $org['centerManager']->id)->first();

    expect(presenceCountFor($org['centerManager']->id))->toBe(1)
        ->and($note->title)->toBe('Employee Offline / Connection Lost')
        ->and($note->body)->toContain('Emp A')
        ->and($note->body)->toContain('Center A')
        ->and($note->body)->toContain('Offline duration:')
        ->and($row->connectivity_state)->toBeNull()
        ->and($row->alert_kind)->toBe('connection_lost')
        ->and(app(MobilizerPresenceService::class)->liveStatus($attendance->fresh()))->toBe(MobilizerPresenceService::CONNECTION_LOST)
        ->and(presenceCountFor($org['otherManager']->id))->toBe(0);

    Carbon::setTestNow($start->copy()->addMinutes(19));
    $this->artisan('presence:monitor')->assertSuccessful();
    expect(presenceCountFor($org['centerManager']->id))->toBe(1)
        ->and(AttendancePresence::query()->where('attendance_id', $attendance->id)->value('connectivity_state'))->toBeNull();

    Carbon::setTestNow($start->copy()->addMinutes(20));
    $this->artisan('presence:monitor')->assertSuccessful();
    expect(presenceCountFor($org['centerManager']->id))->toBe(2)
        ->and(AppNotification::query()->where('user_id', $org['centerManager']->id)->pluck('title')->unique()->all())
        ->toBe(['Employee Offline / Connection Lost'])
        ->and(AttendancePresence::query()->where('attendance_id', $attendance->id)->value('connectivity_state'))->toBeNull();
});

it('shows GPS signal lost while the heartbeat continues', function () {
    $org = presenceOrg();
    $start = Carbon::parse('2026-11-25 10:00:00', 'Asia/Kolkata');
    $attendance = openMobilizerShift($org['empA'], $start);
    Carbon::setTestNow($start->copy()->addMinute());

    $this->actingAs($org['userA'], 'sanctum')
        ->postJson('/api/employee/presence/heartbeat', [
            'attendance_id' => $attendance->id,
            'gps_services_enabled' => true,
            'gps_fix_valid' => false,
        ])
        ->assertOk()
        ->assertJsonPath('status', MobilizerPresenceService::GPS_SIGNAL_LOST)
        ->assertJsonPath('confirmed_internet_off', false);

    expect(AppNotification::query()->where('user_id', $org['centerManager']->id)->value('title'))
        ->toBe('GPS Signal Lost')
        ->and(AttendancePresence::query()->where('attendance_id', $attendance->id)->value('connectivity_state'))->toBeNull()
        ->and(presenceCountFor($org['centerManager']->id))->toBe(1);

    $this->artisan('presence:monitor')->assertSuccessful();
    expect(presenceCountFor($org['centerManager']->id))->toBe(1);
});

it('notifies once when a heartbeat and GPS fix return to Online / Tracking', function () {
    $org = presenceOrg();
    $start = Carbon::parse('2026-11-25 10:00:00', 'Asia/Kolkata');
    $attendance = openMobilizerShift($org['empA'], $start);

    Carbon::setTestNow($start->copy()->addMinutes(5));
    $this->artisan('presence:monitor')->assertSuccessful();
    expect(presenceCountFor($org['centerManager']->id))->toBe(1);

    Carbon::setTestNow($start->copy()->addMinutes(6));
    $this->actingAs($org['userA'], 'sanctum')
        ->postJson('/api/employee/route-points/batch', [
            'attendance_id' => $attendance->id,
            'points' => [[
                'local_uuid' => 'fix-restored-1',
                'latitude' => 18.5222,
                'longitude' => 73.8588,
                'accuracy' => 8,
                'recorded_at' => $start->copy()->addMinutes(6)->toIso8601String(),
                'source' => 'test',
            ]],
        ])
        ->assertCreated();

    expect(app(MobilizerPresenceService::class)->liveStatus($attendance->fresh()))->toBe(MobilizerPresenceService::ONLINE)
        ->and(presenceCountFor($org['centerManager']->id))->toBe(2)
        ->and(AppNotification::query()->where('user_id', $org['centerManager']->id)->orderBy('id')->pluck('title')->all())
        ->toBe(['Employee Offline / Connection Lost', 'Online / Tracking']);

    $this->artisan('presence:monitor')->assertSuccessful();
    expect(presenceCountFor($org['centerManager']->id))->toBe(2);
});

it('notifies once when GPS is restored and clears GPS OFF', function () {
    $org = presenceOrg();
    $start = Carbon::parse('2026-11-25 10:00:00', 'Asia/Kolkata');
    $attendance = openMobilizerShift($org['empA'], $start);
    Carbon::setTestNow($start->copy()->addMinute());

    $this->actingAs($org['userA'], 'sanctum')->postJson('/api/employee/presence/heartbeat', [
        'attendance_id' => $attendance->id,
        'gps_services_enabled' => false,
        'gps_fix_valid' => false,
        'events' => [[
            'local_uuid' => 'gps-off-2',
            'event_type' => 'gps_off',
            'recorded_at' => now()->toIso8601String(),
        ]],
    ])->assertOk();

    Carbon::setTestNow($start->copy()->addMinutes(2));
    $this->actingAs($org['userA'], 'sanctum')->postJson('/api/employee/presence/heartbeat', [
        'attendance_id' => $attendance->id,
        'gps_services_enabled' => true,
        'gps_fix_valid' => true,
        'latitude' => 18.53,
        'longitude' => 73.86,
        'events' => [[
            'local_uuid' => 'gps-on-2',
            'event_type' => 'gps_on',
            'recorded_at' => now()->toIso8601String(),
            'latitude' => 18.53,
            'longitude' => 73.86,
        ]],
    ])->assertOk()->assertJsonPath('status', MobilizerPresenceService::ONLINE);

    $row = AttendancePresence::query()->where('attendance_id', $attendance->id)->first();

    expect($row->gps_services_enabled)->toBeTrue()
        ->and($row->alert_kind)->toBeNull()
        ->and(AppNotification::query()->where('user_id', $org['centerManager']->id)->orderBy('id')->pluck('title')->all())
        ->toBe(['GPS OFF', 'GPS Restored']);

    $this->actingAs($org['userA'], 'sanctum')->postJson('/api/employee/presence/heartbeat', [
        'attendance_id' => $attendance->id,
        'gps_services_enabled' => true,
        'gps_fix_valid' => true,
        'latitude' => 18.53,
        'longitude' => 73.86,
    ])->assertOk();

    expect(presenceCountFor($org['centerManager']->id))->toBe(2);
});

it('stops alerts after punch out and does not send a new offline alert', function () {
    $org = presenceOrg();
    $start = Carbon::parse('2026-11-25 10:00:00', 'Asia/Kolkata');
    $attendance = openMobilizerShift($org['empA'], $start);
    Carbon::setTestNow($start->copy()->addMinutes(5));
    $this->artisan('presence:monitor')->assertSuccessful();
    expect(presenceCountFor($org['centerManager']->id))->toBe(1);

    Carbon::setTestNow($start->copy()->addMinutes(6));
    $attendance->update(['punch_out_time' => '10:06:00']);
    app(MobilizerPresenceService::class)->stopMonitoring($attendance->fresh());

    Carbon::setTestNow($start->copy()->addMinutes(40));
    $this->artisan('presence:monitor')->assertSuccessful();

    expect(presenceCountFor($org['centerManager']->id))->toBe(1)
        ->and(app(MobilizerPresenceService::class)->liveStatus($attendance->fresh()))->toBeNull()
        ->and(AttendancePresence::query()->where('attendance_id', $attendance->id)->value('alert_kind'))->toBeNull();
});

it('does not alert employees who are not punched in or are not mobilizers', function () {
    $org = presenceOrg();
    $start = Carbon::parse('2026-11-25 10:00:00', 'Asia/Kolkata');
    Carbon::setTestNow($start);
    Attendance::query()->create([
        'employee_id' => $org['trainer']->id,
        'attendance_date' => $start->toDateString(),
        'punch_in_time' => '09:00:00',
        'punch_in_latitude' => 18.5,
        'punch_in_longitude' => 73.8,
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);

    Carbon::setTestNow($start->copy()->addHours(2));
    $this->artisan('presence:monitor')->assertSuccessful();

    expect(AppNotification::query()->where('type', 'mobilizer_presence')->count())->toBe(0);

    $rows = $this->actingAs($org['centerManager'], 'sanctum')
        ->getJson('/api/dashboard')
        ->json('data.mobilizer_presence');
    expect(collect($rows)->pluck('employee_name'))->not->toContain('Trainer A')
        ->and(collect($rows)->pluck('employee_name'))->not->toContain('Emp B');
});

it('stores synced internet off history and shows it only after the device event arrives', function () {
    $org = presenceOrg();
    $start = Carbon::parse('2026-11-25 10:00:00', 'Asia/Kolkata');
    $attendance = openMobilizerShift($org['empA'], $start);
    Carbon::setTestNow($start->copy()->addMinutes(6));
    $this->artisan('presence:monitor')->assertSuccessful();

    $before = $this->actingAs($org['centerManager'], 'sanctum')
        ->getJson('/api/dashboard')
        ->json('data.mobilizer_presence.0');

    expect($before['status'])->toBe('Employee Offline / Connection Lost')
        ->and($before['confirmed_internet_off'])->toBeFalse()
        ->and(collect($before['history'])->pluck('status'))->not->toContain('Internet Disconnected / Offline')
        ->and(EmployeePresenceEvent::query()->count())->toBe(0);

    Carbon::setTestNow($start->copy()->addMinutes(7));
    $this->actingAs($org['userA'], 'sanctum')
        ->postJson('/api/employee/presence/heartbeat', [
            'attendance_id' => $attendance->id,
            'heartbeat' => false,
            'events' => [[
                'local_uuid' => 'net-off-1',
                'event_type' => 'internet_off',
                'recorded_at' => $start->copy()->addMinute()->toIso8601String(),
                'latitude' => 18.5204,
                'longitude' => 73.8567,
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('status', MobilizerPresenceService::INTERNET_OFF)
        ->assertJsonPath('confirmed_internet_off', true);

    $after = $this->actingAs($org['centerManager'], 'sanctum')
        ->getJson('/api/dashboard')
        ->json('data.mobilizer_presence.0');
    $stored = EmployeePresenceEvent::query()->where('event_type', 'internet_off')->first();

    expect($stored)->not->toBeNull()
        ->and($after['status'])->toBe('Internet Disconnected / Offline')
        ->and($after['confirmed_internet_off'])->toBeTrue()
        ->and(collect($after['history'])->pluck('status'))->toContain('Internet Disconnected / Offline')
        ->and(AttendancePresence::query()->where('attendance_id', $attendance->id)->value('connectivity_state'))->toBe('off');
});

it('registers the presence monitor on the scheduler', function () {
    $commands = collect(app(Schedule::class)->events())
        ->map(fn ($event) => (string) ($event->command ?? ''))
        ->implode(' ');

    expect($commands)->toContain('presence:monitor');
    expect(Artisan::all())->toHaveKey('presence:monitor');
});
