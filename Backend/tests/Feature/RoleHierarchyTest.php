<?php

use App\Enums\AdmissionStatus;
use App\Enums\CenterStaffRole;
use App\Enums\UserRole;
use App\Filament\Resources\Centers\Pages\CreateCenter;
use App\Filament\Resources\Centers\Pages\ListCenters;
use App\Models\Admission;
use App\Models\Attendance;
use App\Models\User;
use App\Services\OrganizationAccessService;
use App\Support\AttendanceCalendar;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('scopes a center coordinator to assigned centers and keeps them read-only', function () {
    $org = seedOrg();
    $coordinator = User::create([
        'name' => 'Coordinator',
        'email' => 'coord@test.local',
        'login_id' => 'coordinator',
        'password' => 'Coordinator@123',
        'role' => UserRole::CenterCoordinator->value,
        'is_active' => true,
    ]);
    $second = User::create([
        'name' => 'Second Coordinator',
        'email' => 'coord2@test.local',
        'login_id' => 'coordinator2',
        'password' => 'Coordinator@123',
        'role' => UserRole::CenterCoordinator->value,
        'is_active' => true,
    ]);
    $coordinator->coordinatedCenters()->attach([$org['centerA']->id]);
    $second->coordinatedCenters()->attach([$org['centerA']->id]);

    expect($org['centerA']->coordinators()->count())->toBe(2);

    $access = app(OrganizationAccessService::class);
    expect($access->visibleCenterIds($coordinator))->toBe([$org['centerA']->id])
        ->and($access->isReadOnlyCenterViewer($coordinator))->toBeTrue()
        ->and($access->canManageCenters($coordinator))->toBeFalse()
        ->and($access->canManageEmployees($coordinator))->toBeFalse()
        ->and($access->creatableOrgUserRoles($coordinator))->toBe([]);

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

    Sanctum::actingAs($coordinator);
    $this->getJson('/api/director/team-attendance/'.$own->id)->assertOk();
    $this->getJson('/api/director/team-attendance/'.$other->id)->assertForbidden();
    $this->getJson('/api/director/centers/'.$org['centerB']->id)->assertForbidden();
    expect($access->canApproveLeave($coordinator, new \App\Models\LeaveRequest([
        'status' => \App\Enums\LeaveStatus::Pending,
        'center_id' => $org['centerA']->id,
        'employee_id' => $org['empA']->id,
    ])))->toBeFalse();

    Livewire::actingAs($coordinator)
        ->test(CreateCenter::class)
        ->assertForbidden();

    Livewire::actingAs($coordinator)
        ->test(ListCenters::class)
        ->assertSee('Center A')
        ->assertDontSee('Center B');

    $listHtml = Livewire::actingAs($org['admin'])
        ->test(ListCenters::class)
        ->assertSee('Sr. No.')
        ->assertSee('Center Name')
        ->assertSee('Scheme')
        ->assertSee('Center Coordinator(s)')
        ->assertSee('Center Manager')
        ->assertSee('Employees')
        ->assertSee('Is Active')
        ->assertSee('Actions')
        ->assertSee('Coordinator')
        ->assertSee('Second Coordinator')
        ->assertSee('CM')
        ->assertSee('Not Assigned')
        ->assertSee('Open Dashboard')
        ->assertSee('Edit')
        ->html();

    expect($listHtml)
        ->toMatch('/fi-badge[^>]*>\s*Coordinator\s*</')
        ->toMatch('/fi-badge[^>]*>\s*Second Coordinator\s*</')
        ->not->toMatch('/fi-badge[^>]*>\s*CM\s*</');

    $cursor = 0;
    foreach ([
        'fi-ta-header-cell-index',
        'fi-ta-header-cell-name',
        'fi-ta-header-cell-scheme.name',
        'fi-ta-header-cell-coordinators.name',
        'fi-ta-header-cell-center-managers.name',
        'fi-ta-header-cell-employees-count',
        'fi-ta-header-cell-is-active',
        'fi-ta-header-cell fi-align-end',
    ] as $marker) {
        $position = strpos($listHtml, $marker, $cursor);
        expect($position)->not->toBeFalse();
        $cursor = $position + strlen($marker);
    }
});

it('lets one manager cover several centers and rejects a second active manager', function () {
    $org = seedOrg();
    $org['centerManager']->managedCenters()->attach($org['centerB']->id);

    expect($org['centerManager']->fresh()->managedCenters()->pluck('centers.id')->all())
        ->toContain($org['centerA']->id, $org['centerB']->id);

    $second = User::create([
        'name' => 'Other Manager',
        'email' => 'other-manager@test.local',
        'login_id' => 'othermgr2',
        'password' => 'CenterMgr@123',
        'role' => UserRole::CenterManager->value,
        'is_active' => true,
    ]);

    expect(fn () => $second->managedCenters()->attach($org['centerA']->id))
        ->toThrow(QueryException::class);
});

it('blocks food and hostel supervisors from submitting admissions and keeps the draft', function () {
    $org = seedOrg();
    $org['empA']->update(['staff_role' => CenterStaffRole::FoodHostelSupervisor->value]);

    $admission = Admission::create([
        'scheme_id' => $org['projectA']->id,
        'center_id' => $org['centerA']->id,
        'employee_id' => $org['empA']->id,
        'created_by_user_id' => $org['userA']->id,
        'status' => AdmissionStatus::Draft,
        'current_step' => 1,
    ]);

    $this->actingAs($org['userA'], 'sanctum')
        ->postJson('/api/admissions/'.$admission->id.'/submit')
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'Food & Hostel Supervisors cannot submit admissions.']);

    expect($admission->fresh()->status)->toBe(AdmissionStatus::Draft);

    $me = $this->actingAs($org['userA'], 'sanctum')
        ->getJson('/api/me')
        ->assertOk();

    expect($me->json('permissions'))
        ->toContain('food_expenses', 'hostel_expenses', 'daily_food_consumption', 'hostel_occupancy');
});
