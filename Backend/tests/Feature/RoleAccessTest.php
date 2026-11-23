<?php

use App\Enums\AdmissionTargetType;
use App\Enums\UserRole;
use App\Filament\Resources\Centers\Pages\ListCenters;
use App\Models\Admission;
use App\Models\AdmissionTarget;
use App\Models\Attendance;
use App\Models\Center;
use App\Models\Employee;
use App\Models\EmployeeRoutePoint;
use App\Models\User;
use App\Services\OrganizationAccessService;
use App\Support\AttendanceCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

uses(RefreshDatabase::class);

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

it('lets a director view routes only for assigned centers', function () {
    $org = seedOrg();
    $org['director']->directedCenters()->sync([$org['centerA']->id]);
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
    $this->getJson('/api/director/route-tracking/'.$other->id)->assertForbidden();

    $unassigned = User::create([
        'name' => 'Unassigned Director',
        'email' => 'unassigned-director@test.local',
        'login_id' => 'unassigneddir',
        'password' => Hash::make('Director@123'),
        'role' => UserRole::Director->value,
        'is_active' => true,
    ]);
    Sanctum::actingAs($unassigned);
    $this->getJson('/api/director/route-tracking/'.$own->id)->assertForbidden();
    $this->getJson('/api/director/route-tracking/'.$other->id)->assertForbidden();

    Sanctum::actingAs($org['admin']);
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

    $this->getJson('/api/manager/team-attendance/'.$own->id)->assertForbidden();
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

    expect(EmployeeRoutePoint::query()->count())->toBe(1);
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

    $access = app(OrganizationAccessService::class);
    $ph = $org['projectHead'];

    expect($access->visibleCenterIds($ph))->toBe([])
        ->and($access->canViewEmployee($ph, $org['empA']))->toBeFalse()
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
    $this->getJson('/api/manager/team-attendance/'.$ownAttendance->id)->assertForbidden();
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
    $this->getJson('/api/manager/leaves/'.$ownLeave)->assertForbidden();
    $this->getJson('/api/manager/leaves/'.$hiddenLeave)->assertForbidden();

    $ownAdmission = Admission::create([
        'scheme_id' => $org['projectA']->id,
        'center_id' => $org['centerA']->id,
        'employee_id' => $org['empA']->id,
        'first_name' => 'Own',
        'last_name' => 'Center',
    ]);
    $hiddenAdmission = Admission::create([
        'scheme_id' => $org['projectA']->id,
        'center_id' => $sameProjectOther->id,
        'employee_id' => $empSameProject->id,
        'first_name' => 'Hidden',
        'last_name' => 'Center',
    ]);

    Sanctum::actingAs($ph);
    $this->getJson('/api/manager/admissions/'.$ownAdmission->id)->assertForbidden();
    $this->getJson('/api/manager/admissions/'.$hiddenAdmission->id)->assertForbidden();

    $ownTarget = AdmissionTarget::create([
        'employee_id' => $org['empA']->id,
        'center_id' => $org['centerA']->id,
        'scheme_id' => $org['projectA']->id,
        'target_type' => AdmissionTargetType::Weekly,
        'period_start' => '2026-09-14',
        'period_end' => '2026-09-20',
        'target_count' => 5,
    ]);
    $hiddenTarget = AdmissionTarget::create([
        'employee_id' => $empSameProject->id,
        'center_id' => $sameProjectOther->id,
        'scheme_id' => $org['projectA']->id,
        'target_type' => AdmissionTargetType::Weekly,
        'period_start' => '2026-09-14',
        'period_end' => '2026-09-20',
        'target_count' => 5,
    ]);

    expect($access->canViewAdmissionTarget($ph, $ownTarget->load('employee')))->toBeFalse()
        ->and($access->canViewAdmissionTarget($ph, $hiddenTarget->load('employee')))->toBeFalse()
        ->and($access->admissionTargetQuery($ph)->pluck('id')->all())->toBe([]);

    Livewire::actingAs($ph)
        ->test(ListCenters::class)
        ->assertForbidden();
});

it('lets a project head access centers from different projects when those centers are assigned', function () {
    $org = seedOrg();
    $org['projectHead']->headedCenters()->sync([$org['centerA']->id, $org['centerB']->id]);

    $access = app(OrganizationAccessService::class);
    $ids = $access->visibleCenterIds($org['projectHead']);

    expect($ids)->toBe([])
        ->and($org['projectHead']->fresh()->headedCenters()->pluck('centers.id')->all())
        ->toContain((int) $org['centerA']->id, (int) $org['centerB']->id)
        ->and($access->canViewEmployee($org['projectHead'], $org['empA']))->toBeFalse()
        ->and($access->canViewEmployee($org['projectHead'], $org['empB']))->toBeFalse();
});

it('limits a director to assigned centers and keeps admin organization-wide', function () {
    $org = seedOrg();
    $access = app(OrganizationAccessService::class);
    $org['director']->directedCenters()->sync([$org['centerA']->id]);

    $unassigned = User::create([
        'name' => 'Unassigned Director',
        'email' => 'unassigned-director-access@test.local',
        'login_id' => 'unassigneddir2',
        'password' => Hash::make('Director@123'),
        'role' => UserRole::Director->value,
        'is_active' => true,
    ]);

    expect($org['director']->directedProjects()->count())->toBe(0)
        ->and($access->hasOrganizationWideAccess($org['director']))->toBeFalse()
        ->and($access->hasOrganizationWideAccess($org['admin']))->toBeTrue()
        ->and($access->visibleProjectIds($org['admin']))->toBeNull()
        ->and($access->visibleCenterIds($org['admin']))->toBeNull()
        ->and($access->visibleCenterIds($org['director']))->toBe([$org['centerA']->id])
        ->and($access->visibleProjectIds($org['director']))->toBe([$org['projectA']->id])
        ->and($access->visibleCenterIds($unassigned))->toBe([])
        ->and($access->visibleEmployeeIds($unassigned))->toBe([])
        ->and($access->canViewEmployee($org['director'], $org['empA']))->toBeTrue()
        ->and($access->canViewEmployee($org['director'], $org['empB']))->toBeFalse()
        ->and($access->canViewEmployee($unassigned, $org['empA']))->toBeFalse()
        ->and($access->canManageOrgUser($org['director'], $org['projectHead']))->toBeTrue()
        ->and($access->canManageOrgUser($org['director'], $org['centerManager']))->toBeTrue()
        ->and($access->canManageOrgUser($unassigned, $org['centerManager']))->toBeFalse();
});

it('registers the Center Manager mobile module API routes', function () {
    $uris = collect(Route::getRoutes())
        ->map(fn ($route) => $route->uri())
        ->all();

    foreach ([
        'api/manager/centers',
        'api/manager/employees',
        'api/manager/admissions',
        'api/manager/admission-targets',
        'api/manager/leaves',
        'api/manager/team-attendance',
        'api/manager/route-tracking',
    ] as $uri) {
        expect($uris)->toContain($uri);
    }
});

it('lets a center manager login and load a center-scoped mobile dashboard', function () {
    $org = seedOrg();

    $login = $this->postJson('/api/login', [
        'login_id' => 'centermgr',
        'password' => 'CenterMgr@123',
        'device_id' => 'cm-device-1',
    ])->assertOk();

    $login->assertJsonPath('success', true)
        ->assertJsonPath('user.role', UserRole::CenterManager->value)
        ->assertJsonPath('permissions.0', 'center_manager_dashboard');

    Sanctum::actingAs($org['centerManager']);

    $dashboard = $this->getJson('/api/dashboard')->assertOk();
    $dashboard->assertJsonPath('success', true)
        ->assertJsonPath('data.role', UserRole::CenterManager->value)
        ->assertJsonPath('data.centers', 1)
        ->assertJsonPath('data.employees', 1);

    $employees = $this->getJson('/api/manager/employees')->assertOk()->json('data');
    $ids = collect($employees)->pluck('id')->all();
    expect($ids)->toContain($org['empA']->id)
        ->and($ids)->not->toContain($org['empB']->id);

    $ownTarget = AdmissionTarget::create([
        'employee_id' => $org['empA']->id,
        'center_id' => $org['centerA']->id,
        'scheme_id' => $org['projectA']->id,
        'target_type' => AdmissionTargetType::Weekly,
        'period_start' => '2026-09-14',
        'period_end' => '2026-09-20',
        'target_count' => 4,
    ]);
    $hiddenTarget = AdmissionTarget::create([
        'employee_id' => $org['empB']->id,
        'center_id' => $org['centerB']->id,
        'scheme_id' => $org['projectB']->id,
        'target_type' => AdmissionTargetType::Weekly,
        'period_start' => '2026-09-14',
        'period_end' => '2026-09-20',
        'target_count' => 9,
    ]);

    $targets = $this->getJson('/api/manager/admission-targets')->assertOk()->json('data');
    $targetIds = collect($targets)->pluck('id')->all();
    expect($targetIds)->toContain($ownTarget->id)
        ->and($targetIds)->not->toContain($hiddenTarget->id);

    $this->getJson('/api/manager/admission-targets/'.$ownTarget->id)->assertOk();
    $this->getJson('/api/manager/admission-targets/'.$hiddenTarget->id)->assertForbidden();

    $ownAdmission = Admission::create([
        'scheme_id' => $org['projectA']->id,
        'center_id' => $org['centerA']->id,
        'employee_id' => $org['empA']->id,
        'first_name' => 'Own',
        'last_name' => 'Admission',
    ]);
    $hiddenAdmission = Admission::create([
        'scheme_id' => $org['projectB']->id,
        'center_id' => $org['centerB']->id,
        'employee_id' => $org['empB']->id,
        'first_name' => 'Hidden',
        'last_name' => 'Admission',
    ]);
    $admissions = collect($this->getJson('/api/manager/admissions')->assertOk()->json('data'))->pluck('id')->all();
    expect($admissions)->toContain($ownAdmission->id)
        ->and($admissions)->not->toContain($hiddenAdmission->id);
    $this->getJson('/api/manager/admissions/'.$ownAdmission->id)->assertOk();
    $this->getJson('/api/manager/admissions/'.$hiddenAdmission->id)->assertForbidden();

    $ownLeave = $this->actingAs($org['userA'], 'sanctum')
        ->postJson('/api/leaves', [
            'leave_type' => 'casual',
            'from_date' => '2026-09-20',
            'to_date' => '2026-09-21',
            'reason' => 'Assigned center leave',
        ])
        ->json('data.id');
    $userB = User::query()->where('login_id', '9000000002')->firstOrFail();
    $hiddenLeave = $this->actingAs($userB, 'sanctum')
        ->postJson('/api/leaves', [
            'leave_type' => 'casual',
            'from_date' => '2026-09-20',
            'to_date' => '2026-09-21',
            'reason' => 'Other center leave',
        ])
        ->json('data.id');
    Sanctum::actingAs($org['centerManager']);
    $leaves = collect($this->getJson('/api/manager/leaves')->assertOk()->json('data'))->pluck('id')->all();
    expect($leaves)->toContain($ownLeave)
        ->and($leaves)->not->toContain($hiddenLeave);

    $ownAttendance = Attendance::create([
        'employee_id' => $org['empA']->id,
        'attendance_date' => AttendanceCalendar::today()->toDateString(),
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);
    $hiddenAttendance = Attendance::create([
        'employee_id' => $org['empB']->id,
        'attendance_date' => AttendanceCalendar::today()->toDateString(),
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);
    $attendanceIds = collect($this->getJson('/api/manager/team-attendance')->assertOk()->json('data'))
        ->pluck('employee_id')
        ->all();
    expect($attendanceIds)->toContain($org['empA']->id)
        ->and($attendanceIds)->not->toContain($org['empB']->id);
    $this->getJson('/api/manager/team-attendance/'.$ownAttendance->id)->assertOk();
    $this->getJson('/api/manager/team-attendance/'.$hiddenAttendance->id)->assertForbidden();

    $routeIds = collect($this->getJson('/api/manager/route-tracking')->assertOk()->json('data'))
        ->pluck('employee_id')
        ->all();
    expect($routeIds)->toContain($org['empA']->id)
        ->and($routeIds)->not->toContain($org['empB']->id);
    $this->getJson('/api/manager/route-tracking/'.$ownAttendance->id)->assertOk();
    $this->getJson('/api/manager/route-tracking/'.$hiddenAttendance->id)->assertForbidden();
});

it('lets a center manager punch using the employee attendance API without joining team counts', function () {
    Storage::fake('public');
    $org = seedOrg();

    $this->actingAs($org['centerManager'], 'sanctum')
        ->post('/api/attendance/punch-in', [
            'latitude' => 18.52,
            'longitude' => 73.85,
            'location_address' => 'Pune',
            'photo' => UploadedFile::fake()->image('cm-in.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    $today = $this->actingAs($org['centerManager']->fresh(), 'sanctum')
        ->getJson('/api/attendance/today')
        ->assertOk();

    expect($today->json('data.attendance.punch_in_time'))->not->toBeNull()
        ->and($today->json('data.punch_in_allowed'))->toBeFalse();

    $dashboard = $this->actingAs($org['centerManager']->fresh(), 'sanctum')
        ->getJson('/api/dashboard')
        ->assertOk();

    expect($dashboard->json('data.employees'))->toBe(1)
        ->and($dashboard->json('data.punched_in_today'))->toBe(0);

    $employeeIds = collect(
        $this->actingAs($org['centerManager']->fresh(), 'sanctum')
            ->getJson('/api/manager/employees')
            ->assertOk()
            ->json('data'),
    )->pluck('id')->all();

    expect($employeeIds)->toContain($org['empA']->id)
        ->and($employeeIds)->not->toContain($org['centerManager']->fresh()->employee_id);
});

it('lets a director use assigned-center operations without admin system controls', function () {
    $org = seedOrg();
    $unchanged = [
        'director' => $org['director']->only(['id', 'role', 'is_active', 'login_id']),
        'admin' => $org['admin']->only(['id', 'role', 'is_active', 'login_id']),
        'manager_centers' => $org['centerManager']->managedCenters()->pluck('centers.id')->all(),
        'head_centers' => $org['projectHead']->headedCenters()->pluck('centers.id')->all(),
    ];

    $this->postJson('/api/login', [
        'login_id' => 'fielddirector',
        'password' => 'Director@123',
        'device_id' => 'director-device-1',
    ])->assertOk()
        ->assertJsonPath('user.role', UserRole::Director->value)
        ->assertJsonPath('permissions', [
            'director_dashboard',
            'attendance_view_assigned',
            'route_tracking_view_assigned',
            'leave_view_assigned',
            'expense_approve_assigned',
            'expense_payment',
            'admissions_view_assigned',
            'admission_target_view_assigned',
        ]);

    $this->actingAs($org['director'], 'sanctum')
        ->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonPath('data.role', UserRole::Director->value)
        ->assertJsonPath('data.centers', 2);

    $this->actingAs($org['director'], 'sanctum')
        ->getJson('/api/director/centers')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Center A'])
        ->assertJsonFragment(['name' => 'Center B']);

    $unassigned = User::create([
        'name' => 'No Centers',
        'email' => 'no-centers@test.local',
        'login_id' => 'nocenters',
        'password' => Hash::make('Director@123'),
        'role' => UserRole::Director->value,
        'is_active' => true,
    ]);
    $this->actingAs($unassigned, 'sanctum')
        ->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonPath('data.centers', 0);
    expect($this->actingAs($unassigned, 'sanctum')->getJson('/api/director/centers')->assertOk()->json('data'))->toBe([])
        ->and(collect($this->actingAs($unassigned, 'sanctum')->getJson('/api/director/employees')->assertOk()->json('data'))->pluck('id')->all())->toBe([]);

    $adminCenters = collect($this->actingAs($org['admin'], 'sanctum')->getJson('/api/director/centers')->assertOk()->json('data'))->pluck('name');
    expect($adminCenters)->toContain('Center A', 'Center B');

    $this->actingAs($org['director'], 'sanctum')
        ->getJson('/api/director/employees')
        ->assertOk();

    $this->actingAs($org['director'], 'sanctum')
        ->getJson('/api/director/expenses?status=pending')
        ->assertOk();

    $this->actingAs($org['director'], 'web')
        ->get('/admin')
        ->assertOk()
        ->assertSee('Director Dashboard')
        ->assertSee('Main Dashboard')
        ->assertSee('Center Dashboard')
        ->assertSee('Team Management')
        ->assertSee('Expenses')
        ->assertDontSee('App Update Settings')
        ->assertDontSee('Device Management')
        ->assertDontSee('System');

    $this->actingAs($org['director'])->get('/admin/users')->assertOk();
    $this->actingAs($org['director'])->get('/admin/centers')->assertOk()->assertSee('Center A')->assertSee('Center B');
    $this->actingAs($org['director'])->get('/admin/expense-claims')->assertOk();
    $this->actingAs($org['director'])->get('/admin/app-update-settings')->assertForbidden();
    $this->actingAs($org['director'])->get('/admin/device-management')->assertForbidden();
    $this->actingAs($org['director'])->get('/admin/directors')->assertForbidden();

    $this->flushSession();

    $this->actingAs($org['admin'], 'web')->get('/admin/app-update-settings')->assertOk();
    $this->actingAs($org['admin'], 'web')->get('/admin/device-management')->assertOk();

    expect($org['director']->fresh()->only(['id', 'role', 'is_active', 'login_id']))->toBe($unchanged['director'])
        ->and($org['admin']->fresh()->only(['id', 'role', 'is_active', 'login_id']))->toBe($unchanged['admin'])
        ->and($org['centerManager']->fresh()->managedCenters()->pluck('centers.id')->all())->toBe($unchanged['manager_centers'])
        ->and($org['projectHead']->fresh()->headedCenters()->pluck('centers.id')->all())->toBe($unchanged['head_centers']);
});
