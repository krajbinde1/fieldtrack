<?php

use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Center;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use App\Support\AttendanceCalendar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function seedOrg(): array
{
    $projectA = Project::create(['name' => 'Project A', 'code' => 'PA', 'is_active' => true]);
    $projectB = Project::create(['name' => 'Project B', 'code' => 'PB', 'is_active' => true]);
    $centerA = Center::create(['project_id' => $projectA->id, 'name' => 'Center A', 'code' => 'CA', 'is_active' => true]);
    $centerB = Center::create(['project_id' => $projectB->id, 'name' => 'Center B', 'code' => 'CB', 'is_active' => true]);

    $director = User::create([
        'name' => 'Director',
        'email' => 'dir@test.local',
        'login_id' => 'director',
        'password' => Hash::make('Director@123'),
        'role' => UserRole::Director->value,
        'is_active' => true,
    ]);

    $projectHead = User::create([
        'name' => 'PH',
        'email' => 'ph@test.local',
        'login_id' => 'projecthead',
        'password' => Hash::make('ProjectHead@123'),
        'role' => UserRole::ProjectHead->value,
        'is_active' => true,
    ]);
    $projectHead->headedProjects()->attach($projectA->id);

    $centerManager = User::create([
        'name' => 'CM',
        'email' => 'cm@test.local',
        'login_id' => 'centermgr',
        'password' => Hash::make('CenterMgr@123'),
        'role' => UserRole::CenterManager->value,
        'is_active' => true,
    ]);
    $centerManager->managedCenters()->attach($centerA->id);

    $empA = Employee::create([
        'center_id' => $centerA->id,
        'full_name' => 'Emp A',
        'mobile' => '9000000001',
        'status' => true,
    ]);
    $userA = User::create([
        'employee_id' => $empA->id,
        'name' => 'Emp A',
        'email' => 'empa@test.local',
        'login_id' => '9000000001',
        'password' => Hash::make('Employee@123'),
        'role' => UserRole::Employee->value,
        'is_active' => true,
    ]);

    $empB = Employee::create([
        'center_id' => $centerB->id,
        'full_name' => 'Emp B',
        'mobile' => '9000000002',
        'status' => true,
    ]);
    User::create([
        'employee_id' => $empB->id,
        'name' => 'Emp B',
        'email' => 'empb@test.local',
        'login_id' => '9000000002',
        'password' => Hash::make('Employee@123'),
        'role' => UserRole::Employee->value,
        'is_active' => true,
    ]);

    return compact('director', 'projectHead', 'centerManager', 'empA', 'empB', 'userA');
}

it('blocks project head from another project route record', function () {
    $org = seedOrg();
    $attendance = Attendance::create([
        'employee_id' => $org['empB']->id,
        'attendance_date' => AttendanceCalendar::today()->toDateString(),
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);

    Sanctum::actingAs($org['projectHead']);

    $this->getJson('/api/manager/route-tracking/'.$attendance->id)
        ->assertForbidden();
});

it('allows director to view every employee route', function () {
    $org = seedOrg();
    $attendance = Attendance::create([
        'employee_id' => $org['empB']->id,
        'attendance_date' => AttendanceCalendar::today()->toDateString(),
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);

    Sanctum::actingAs($org['director']);

    $this->getJson('/api/director/route-tracking/'.$attendance->id)
        ->assertOk();
});

it('prevents duplicate punch in', function () {
    Storage::fake('public');
    $org = seedOrg();
    $photo = UploadedFile::fake()->image('punch.jpg');

    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/attendance/punch-in', [
            'latitude' => 18.52,
            'longitude' => 73.85,
            'location_address' => 'Pune',
            'photo' => $photo,
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/attendance/punch-in', [
            'latitude' => 18.52,
            'longitude' => 73.85,
            'location_address' => 'Pune',
            'photo' => UploadedFile::fake()->image('punch2.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'Already punched in.']);
});

it('blocks center manager from another center attendance', function () {
    $org = seedOrg();
    $attendance = Attendance::create([
        'employee_id' => $org['empB']->id,
        'attendance_date' => AttendanceCalendar::today()->toDateString(),
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);

    Sanctum::actingAs($org['centerManager']);

    $this->getJson('/api/manager/team-attendance/'.$attendance->id)
        ->assertForbidden();
});

it('lets project head view assigned project attendance and blocks unrelated employees', function () {
    $org = seedOrg();
    $own = Attendance::create([
        'employee_id' => $org['empA']->id,
        'attendance_date' => AttendanceCalendar::today()->toDateString(),
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);
    $other = Attendance::create([
        'employee_id' => $org['empB']->id,
        'attendance_date' => AttendanceCalendar::today()->toDateString(),
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);

    Sanctum::actingAs($org['projectHead']);

    $this->getJson('/api/manager/team-attendance/'.$own->id)->assertOk();
    $this->getJson('/api/manager/team-attendance/'.$other->id)->assertForbidden();
});

it('records punch out, working hours, and stops a second punch out', function () {
    Storage::fake('public');
    $org = seedOrg();

    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/attendance/punch-in', [
            'latitude' => 18.52,
            'longitude' => 73.85,
            'location_address' => 'Pune',
            'photo' => UploadedFile::fake()->image('in.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/attendance/punch-out', [
            'latitude' => 18.53,
            'longitude' => 73.86,
            'location_address' => 'Pune Out',
            'photo' => UploadedFile::fake()->image('out.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertOk();

    $attendance = Attendance::query()->where('employee_id', $org['empA']->id)->first();
    expect($attendance->punch_out_time)->not->toBeNull()
        ->and($attendance->working_hours)->not->toBeNull()
        ->and($attendance->total_working_minutes)->toBeGreaterThanOrEqual(0);

    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/attendance/punch-out', [
            'latitude' => 18.53,
            'longitude' => 73.86,
            'location_address' => 'Pune Out',
            'photo' => UploadedFile::fake()->image('out2.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'Already punched out.']);
});

it('saves route points once for the same local uuid', function () {
    Storage::fake('public');
    $org = seedOrg();

    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/attendance/punch-in', [
            'latitude' => 18.5200,
            'longitude' => 73.8500,
            'location_address' => 'Pune',
            'photo' => UploadedFile::fake()->image('in.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    $attendance = Attendance::query()->where('employee_id', $org['empA']->id)->first();
    $payload = [
        'attendance_id' => $attendance->id,
        'points' => [[
            'local_uuid' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'latitude' => 18.5204,
            'longitude' => 73.8567,
            'accuracy' => 12,
            'recorded_at' => now()->toIso8601String(),
            'source' => 'test',
        ]],
    ];

    $this->actingAs($org['userA'], 'sanctum')
        ->postJson('/api/employee/route-points/batch', $payload)
        ->assertCreated()
        ->assertJsonPath('inserted', 1);

    $this->actingAs($org['userA'], 'sanctum')
        ->postJson('/api/employee/route-points/batch', $payload)
        ->assertCreated()
        ->assertJsonPath('skipped', 1);

    expect(\App\Models\EmployeeRoutePoint::query()->count())->toBe(1);
});

it('keeps an open punch after a new mobile login', function () {
    Storage::fake('public');
    $org = seedOrg();

    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/attendance/punch-in', [
            'latitude' => 18.52,
            'longitude' => 73.85,
            'location_address' => 'Pune',
            'photo' => UploadedFile::fake()->image('in.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    $this->postJson('/api/login', [
        'login_id' => '9000000001',
        'password' => 'Employee@123',
        'device_id' => 'device-2',
    ])->assertOk()->assertJsonPath('success', true);

    $response = $this->actingAs($org['userA']->fresh(), 'sanctum')
        ->getJson('/api/attendance/today')
        ->assertOk();

    expect($response->json('data.attendance.punch_in_time'))->not->toBeNull()
        ->and($response->json('data.attendance.punch_out_time'))->toBeNull()
        ->and($response->json('data.punch_in_allowed'))->toBeFalse();
});
