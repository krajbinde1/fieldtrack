<?php

use App\Enums\UserRole;
use App\Models\Attendance;
use App\Support\AttendanceCalendar;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

it('lets a director list every active center and open a selected center dashboard', function () {
    $org = seedOrg();

    Attendance::create([
        'employee_id' => $org['empA']->id,
        'attendance_date' => AttendanceCalendar::today()->toDateString(),
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);

    Sanctum::actingAs($org['director']);

    $centers = $this->getJson('/api/director/centers')->assertOk()->json('data');
    $ids = collect($centers)->pluck('id')->all();
    expect($ids)->toContain($org['centerA']->id)
        ->and($ids)->toContain($org['centerB']->id);

    $centerA = collect($centers)->firstWhere('id', $org['centerA']->id);
    expect($centerA['name'])->toBe('Center A')
        ->and($centerA['scheme_name'])->toBe('Project A')
        ->and($centerA['center_manager_name'])->toBe('CM')
        ->and($centerA['employees'])->toBe(1)
        ->and($centerA['punched_in_today'])->toBe(1)
        ->and($centerA['active_routes'])->toBe(1);

    $search = $this->getJson('/api/director/centers?search=Center%20B')->assertOk()->json('data');
    expect(collect($search)->pluck('id')->all())->toBe([$org['centerB']->id]);

    $dashboard = $this->getJson('/api/dashboard?center_id='.$org['centerA']->id)->assertOk();
    $dashboard->assertJsonPath('data.employees', 1)
        ->assertJsonPath('data.punched_in_today', 1)
        ->assertJsonPath('data.active_routes', 1)
        ->assertJsonPath('data.center.id', $org['centerA']->id)
        ->assertJsonPath('data.center.name', 'Center A')
        ->assertJsonPath('data.center.center_manager_name', 'CM');

    $other = $this->getJson('/api/dashboard?center_id='.$org['centerB']->id)->assertOk();
    expect($other->json('data.employees'))->toBe(1)
        ->and($other->json('data.punched_in_today'))->toBe(0);

    $employees = collect($this->getJson('/api/director/employees?center_id='.$org['centerA']->id)
        ->assertOk()
        ->json('data'))->pluck('id')->all();
    expect($employees)->toContain($org['empA']->id)
        ->and($employees)->not->toContain($org['empB']->id);

    $attendance = collect($this->getJson('/api/director/team-attendance?center_id='.$org['centerA']->id)
        ->assertOk()
        ->json('data'))->pluck('employee_id')->all();
    expect($attendance)->toContain($org['empA']->id)
        ->and($attendance)->not->toContain($org['empB']->id);
});

it('does not restrict director center access to center manager assignments', function () {
    $org = seedOrg();
    $unassigned = \App\Models\User::create([
        'name' => 'Unassigned CM',
        'email' => 'unassigned-cm@test.local',
        'login_id' => 'unassignedcm',
        'password' => Hash::make('CenterMgr@123'),
        'role' => UserRole::CenterManager->value,
        'is_active' => true,
    ]);
    $unassigned->managedCenters()->attach($org['centerB']->id);

    Sanctum::actingAs($org['director']);

    $this->getJson('/api/director/centers/'.$org['centerB']->id)
        ->assertOk()
        ->assertJsonPath('data.center_manager_name', 'Unassigned CM');

    $this->getJson('/api/dashboard?center_id='.$org['centerB']->id)
        ->assertOk()
        ->assertJsonPath('data.center.id', $org['centerB']->id);
});

it('keeps center manager dashboard unscoped by other centers', function () {
    $org = seedOrg();

    Sanctum::actingAs($org['centerManager']);

    $this->getJson('/api/dashboard?center_id='.$org['centerB']->id)
        ->assertForbidden();

    $own = $this->getJson('/api/dashboard')->assertOk();
    expect($own->json('data.centers'))->toBe(1)
        ->and($own->json('data.employees'))->toBe(1);
});
