<?php

use App\Enums\FieldActivityType;
use App\Models\Attendance;
use App\Models\Center;
use App\Models\FieldActivity;
use App\Support\AttendanceCalendar;
use Laravel\Sanctum\Sanctum;

it('lets a center manager combine and filter dashboard counts across assigned centers', function () {
    $org = seedOrg();
    $org['centerManager']->managedCenters()->attach($org['centerB']->id);
    $hidden = Center::create([
        'scheme_id' => $org['projectA']->id,
        'name' => 'Hidden Center',
        'code' => 'CH',
        'is_active' => true,
    ]);

    Attendance::create([
        'employee_id' => $org['empA']->id,
        'attendance_date' => AttendanceCalendar::today()->toDateString(),
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);

    FieldActivity::query()->create([
        'employee_id' => $org['empA']->id,
        'user_id' => $org['userA']->id,
        'center_id' => $org['centerA']->id,
        'scheme_id' => $org['projectA']->id,
        'activity_type' => FieldActivityType::FollowUp,
        'activity_name' => 'Today follow-up',
        'activity_at' => AttendanceCalendar::today()->setTime(11, 0),
        'photo_path' => 'field-activities/today.jpg',
        'latitude' => 18.52,
        'longitude' => 73.85,
        'location' => 'Pune',
    ]);

    Sanctum::actingAs($org['centerManager']);

    $all = $this->getJson('/api/dashboard')->assertOk();
    expect($all->json('data.centers'))->toBe(2)
        ->and($all->json('data.employees'))->toBe(2)
        ->and($all->json('data.punched_in_today'))->toBe(1)
        ->and($all->json('data.active_routes'))->toBe(1)
        ->and($all->json('data.field_activities_today'))->toBe(1);

    $this->getJson('/api/dashboard?center_id='.$org['centerA']->id)
        ->assertOk()
        ->assertJsonPath('data.employees', 1)
        ->assertJsonPath('data.punched_in_today', 1)
        ->assertJsonPath('data.active_routes', 1)
        ->assertJsonPath('data.field_activities_today', 1)
        ->assertJsonPath('data.center.id', $org['centerA']->id);

    $this->getJson('/api/dashboard?center_id='.$org['centerB']->id)
        ->assertOk()
        ->assertJsonPath('data.employees', 1)
        ->assertJsonPath('data.punched_in_today', 0)
        ->assertJsonPath('data.active_routes', 0)
        ->assertJsonPath('data.field_activities_today', 0);

    $this->getJson('/api/dashboard?center_id='.$hidden->id)->assertForbidden();

    $employeesA = collect($this->getJson('/api/manager/employees?center_id='.$org['centerA']->id)
        ->assertOk()
        ->json('data'))->pluck('id')->all();
    expect($employeesA)->toContain($org['empA']->id)
        ->and($employeesA)->not->toContain($org['empB']->id);

    $employeesB = collect($this->getJson('/api/manager/employees?center_id='.$org['centerB']->id)
        ->assertOk()
        ->json('data'))->pluck('id')->all();
    expect($employeesB)->toContain($org['empB']->id)
        ->and($employeesB)->not->toContain($org['empA']->id);

    $this->getJson('/api/manager/employees?center_id='.$hidden->id)->assertForbidden();
});

it('lists only assigned centers for a center manager with monitoring stats', function () {
    $org = seedOrg();
    $org['centerManager']->managedCenters()->attach($org['centerB']->id);
    $hidden = Center::create([
        'scheme_id' => $org['projectB']->id,
        'name' => 'Unassigned Center',
        'code' => 'CU',
        'is_active' => true,
    ]);

    Attendance::create([
        'employee_id' => $org['empA']->id,
        'attendance_date' => AttendanceCalendar::today()->toDateString(),
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);

    FieldActivity::query()->create([
        'employee_id' => $org['empA']->id,
        'user_id' => $org['userA']->id,
        'center_id' => $org['centerA']->id,
        'scheme_id' => $org['projectA']->id,
        'activity_type' => FieldActivityType::VillageVisit,
        'activity_name' => 'Village visit',
        'activity_at' => AttendanceCalendar::today()->setTime(10, 0),
        'photo_path' => 'field-activities/visit.jpg',
        'latitude' => 18.52,
        'longitude' => 73.85,
        'location' => 'Pune',
    ]);

    Sanctum::actingAs($org['centerManager']);

    $centers = $this->getJson('/api/manager/centers')->assertOk()->json('data');
    $ids = collect($centers)->pluck('id')->all();
    expect($ids)->toContain($org['centerA']->id)
        ->and($ids)->toContain($org['centerB']->id)
        ->and($ids)->not->toContain($hidden->id);

    $centerA = collect($centers)->firstWhere('id', $org['centerA']->id);
    expect($centerA['name'])->toBe('Center A')
        ->and($centerA['scheme_name'])->toBe('Project A')
        ->and($centerA['employees'])->toBe(1)
        ->and($centerA['punched_in_today'])->toBe(1)
        ->and($centerA['active_routes'])->toBe(1)
        ->and($centerA['field_activities_today'])->toBe(1);

    $centerB = collect($centers)->firstWhere('id', $org['centerB']->id);
    expect($centerB['name'])->toBe('Center B')
        ->and($centerB['scheme_name'])->toBe('Project B')
        ->and($centerB['employees'])->toBe(1)
        ->and($centerB['punched_in_today'])->toBe(0)
        ->and($centerB['field_activities_today'])->toBe(0);

    $this->getJson('/api/director/centers')->assertForbidden();
});
