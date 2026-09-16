<?php

use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Center;
use App\Models\Employee;
use App\Models\Scheme;
use App\Models\User;
use App\Support\AttendanceCalendar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function seedOrg(): array
{
    $projectA = Scheme::create(['name' => 'Project A', 'code' => 'PA', 'is_active' => true]);
    $projectB = Scheme::create(['name' => 'Project B', 'code' => 'PB', 'is_active' => true]);
    $centerA = Center::create(['scheme_id' => $projectA->id, 'name' => 'Center A', 'code' => 'CA', 'is_active' => true]);
    $centerB = Center::create(['scheme_id' => $projectB->id, 'name' => 'Center B', 'code' => 'CB', 'is_active' => true]);

    $admin = User::create([
        'name' => 'Admin',
        'email' => 'dir@test.local',
        'login_id' => 'director',
        'password' => Hash::make('Director@123'),
        'role' => UserRole::Admin->value,
        'is_active' => true,
    ]);

    $director = User::create([
        'name' => 'Director',
        'email' => 'fielddirector@test.local',
        'login_id' => 'fielddirector',
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
    $projectHead->headedCenters()->attach($centerA->id);

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

    return compact('admin', 'director', 'projectHead', 'centerManager', 'empA', 'empB', 'userA', 'centerA', 'centerB', 'projectA', 'projectB');
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

it('allows admin to view every employee route', function () {
    $org = seedOrg();
    $attendance = Attendance::create([
        'employee_id' => $org['empB']->id,
        'attendance_date' => AttendanceCalendar::today()->toDateString(),
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);

    Sanctum::actingAs($org['admin']);

    $this->getJson('/api/director/route-tracking/'.$attendance->id)
        ->assertOk();
});

it('lets a director view routes from every project', function () {
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

    Sanctum::actingAs($org['director']);

    $this->getJson('/api/director/route-tracking/'.$own->id)->assertOk();
    $this->getJson('/api/director/route-tracking/'.$other->id)->assertOk();
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

it('scopes project head access to assigned centers and not every center in the project', function () {
    $org = seedOrg();
    $sameProjectOther = Center::create([
        'scheme_id' => $org['projectA']->id,
        'name' => 'Center A2',
        'code' => 'CA2',
        'is_active' => true,
    ]);
    $empSameProject = Employee::create([
        'center_id' => $sameProjectOther->id,
        'full_name' => 'Emp A2',
        'mobile' => '9000000003',
        'status' => true,
    ]);
    $userSame = User::create([
        'employee_id' => $empSameProject->id,
        'name' => 'Emp A2',
        'email' => 'empa2@test.local',
        'login_id' => '9000000003',
        'password' => Hash::make('Employee@123'),
        'role' => UserRole::Employee->value,
        'is_active' => true,
    ]);

    $access = app(\App\Services\OrganizationAccessService::class);
    $ph = $org['projectHead'];

    expect($access->visibleCenterIds($ph))->toBe([(int) $org['centerA']->id])
        ->and($access->canViewEmployee($ph, $org['empA']))->toBeTrue()
        ->and($access->canViewEmployee($ph, $empSameProject))->toBeFalse()
        ->and($access->canViewEmployee($ph, $org['empB']))->toBeFalse();

    $ownAttendance = Attendance::create([
        'employee_id' => $org['empA']->id,
        'attendance_date' => AttendanceCalendar::today()->toDateString(),
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);
    $hiddenAttendance = Attendance::create([
        'employee_id' => $empSameProject->id,
        'attendance_date' => AttendanceCalendar::today()->toDateString(),
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);

    Sanctum::actingAs($ph);
    $this->getJson('/api/manager/team-attendance/'.$ownAttendance->id)->assertOk();
    $this->getJson('/api/manager/team-attendance/'.$hiddenAttendance->id)->assertForbidden();
    $this->getJson('/api/manager/route-tracking/'.$hiddenAttendance->id)->assertForbidden();

    $ownLeave = $this->actingAs($org['userA'], 'sanctum')
        ->postJson('/api/leaves', [
            'leave_type' => 'casual',
            'from_date' => '2026-09-20',
            'to_date' => '2026-09-21',
            'reason' => 'Assigned center leave',
        ])
        ->json('data.id');
    $hiddenLeave = $this->actingAs($userSame, 'sanctum')
        ->postJson('/api/leaves', [
            'leave_type' => 'casual',
            'from_date' => '2026-09-20',
            'to_date' => '2026-09-21',
            'reason' => 'Unassigned center leave',
        ])
        ->json('data.id');

    Sanctum::actingAs($ph);
    $this->getJson('/api/manager/leaves/'.$ownLeave)->assertOk();
    $this->getJson('/api/manager/leaves/'.$hiddenLeave)->assertForbidden();

    $ownAdmission = \App\Models\Admission::create([
        'scheme_id' => $org['projectA']->id,
        'center_id' => $org['centerA']->id,
        'employee_id' => $org['empA']->id,
        'first_name' => 'Own',
        'last_name' => 'Center',
    ]);
    $hiddenAdmission = \App\Models\Admission::create([
        'scheme_id' => $org['projectA']->id,
        'center_id' => $sameProjectOther->id,
        'employee_id' => $empSameProject->id,
        'first_name' => 'Hidden',
        'last_name' => 'Center',
    ]);

    Sanctum::actingAs($ph);
    $this->getJson('/api/manager/admissions/'.$ownAdmission->id)->assertOk();
    $this->getJson('/api/manager/admissions/'.$hiddenAdmission->id)->assertForbidden();

    $ownTarget = \App\Models\AdmissionTarget::create([
        'employee_id' => $org['empA']->id,
        'center_id' => $org['centerA']->id,
        'scheme_id' => $org['projectA']->id,
        'target_type' => \App\Enums\AdmissionTargetType::Weekly,
        'period_start' => '2026-09-14',
        'period_end' => '2026-09-20',
        'target_count' => 5,
    ]);
    $hiddenTarget = \App\Models\AdmissionTarget::create([
        'employee_id' => $empSameProject->id,
        'center_id' => $sameProjectOther->id,
        'scheme_id' => $org['projectA']->id,
        'target_type' => \App\Enums\AdmissionTargetType::Weekly,
        'period_start' => '2026-09-14',
        'period_end' => '2026-09-20',
        'target_count' => 5,
    ]);

    expect($access->canViewAdmissionTarget($ph, $ownTarget->load('employee')))->toBeTrue()
        ->and($access->canViewAdmissionTarget($ph, $hiddenTarget->load('employee')))->toBeFalse()
        ->and($access->admissionTargetQuery($ph)->pluck('id')->all())->toContain($ownTarget->id)
        ->and($access->admissionTargetQuery($ph)->pluck('id')->all())->not->toContain($hiddenTarget->id);

    \Livewire\Livewire::actingAs($ph)
        ->test(\App\Filament\Resources\Centers\Pages\ListCenters::class)
        ->assertCanSeeTableRecords([$org['centerA']])
        ->assertCanNotSeeTableRecords([$sameProjectOther, $org['centerB']]);
});

it('lets a project head access centers from different projects when those centers are assigned', function () {
    $org = seedOrg();
    $org['projectHead']->headedCenters()->sync([$org['centerA']->id, $org['centerB']->id]);

    $access = app(\App\Services\OrganizationAccessService::class);
    $ids = $access->visibleCenterIds($org['projectHead']);

    expect($ids)->toContain((int) $org['centerA']->id)
        ->and($ids)->toContain((int) $org['centerB']->id)
        ->and($access->canViewEmployee($org['projectHead'], $org['empA']))->toBeTrue()
        ->and($access->canViewEmployee($org['projectHead'], $org['empB']))->toBeTrue();
});

it('gives a director organization-wide access without project or center assignment', function () {
    $org = seedOrg();
    $access = app(\App\Services\OrganizationAccessService::class);

    expect($org['director']->directedProjects()->count())->toBe(0)
        ->and($access->visibleProjectIds($org['director']))->toBeNull()
        ->and($access->visibleCenterIds($org['director']))->toBeNull()
        ->and($access->visibleEmployeeIds($org['director']))->toBeNull()
        ->and($access->canViewEmployee($org['director'], $org['empA']))->toBeTrue()
        ->and($access->canViewEmployee($org['director'], $org['empB']))->toBeTrue()
        ->and($access->canManageOrgUser($org['director'], $org['projectHead']))->toBeTrue()
        ->and($access->canManageOrgUser($org['director'], $org['centerManager']))->toBeTrue();
});
