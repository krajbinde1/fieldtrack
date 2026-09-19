<?php

use App\Enums\AdmissionStatus;
use App\Models\Admission;
use App\Models\Attendance;
use App\Models\User;
use App\Support\AttendanceCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('lets a project head use director monitoring APIs scoped to assigned centers only', function () {
    $org = seedOrg();
    $today = AttendanceCalendar::today()->toDateString();

    Attendance::create([
        'employee_id' => $org['empA']->id,
        'attendance_date' => $today,
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);
    Attendance::create([
        'employee_id' => $org['empB']->id,
        'attendance_date' => $today,
        'punch_in_time' => '09:15:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);
    Admission::create([
        'scheme_id' => $org['projectA']->id,
        'center_id' => $org['centerA']->id,
        'employee_id' => $org['empA']->id,
        'first_name' => 'Assigned',
        'last_name' => 'Confirm',
        'full_name' => 'Assigned Confirm',
        'status' => AdmissionStatus::Confirmed,
        'confirmed_at' => now(),
        'reviewed_by_user_id' => $org['centerManager']->id,
    ]);
    Admission::create([
        'scheme_id' => $org['projectB']->id,
        'center_id' => $org['centerB']->id,
        'employee_id' => $org['empB']->id,
        'first_name' => 'Other',
        'last_name' => 'Confirm',
        'full_name' => 'Other Confirm',
        'status' => AdmissionStatus::Confirmed,
        'confirmed_at' => now(),
    ]);

    Sanctum::actingAs($org['projectHead']);

    $dashboard = $this->getJson('/api/dashboard')->assertOk()->json('data');
    expect($dashboard['active_centers'])->toBe(1)
        ->and($dashboard['centers'])->toBe(1)
        ->and($dashboard['employees'])->toBe(2)
        ->and($dashboard['punched_in_today'])->toBe(1)
        ->and($dashboard['active_routes'])->toBe(1)
        ->and($dashboard['confirmed_admissions'])->toBe(1);

    $centerIds = collect($this->getJson('/api/director/centers')->assertOk()->json('data'))
        ->pluck('id')
        ->all();
    expect($centerIds)->toContain($org['centerA']->id)
        ->and($centerIds)->not->toContain($org['centerB']->id);

    $this->getJson('/api/director/centers/'.$org['centerA']->id)->assertOk();
    $this->getJson('/api/director/centers/'.$org['centerB']->id)->assertForbidden();
    $this->getJson('/api/dashboard?center_id='.$org['centerA']->id)
        ->assertOk()
        ->assertJsonPath('data.employees', 1)
        ->assertJsonPath('data.center.id', $org['centerA']->id);
    $this->getJson('/api/dashboard?center_id='.$org['centerB']->id)->assertForbidden();

    $employeeIds = collect($this->getJson('/api/director/employees')->assertOk()->json('data'))
        ->pluck('employee_id')
        ->filter()
        ->all();
    expect($employeeIds)->toContain($org['empA']->id)
        ->and($employeeIds)->not->toContain($org['empB']->id);

    $confirmed = collect($this->getJson('/api/director/admissions/confirmed-by-center')->assertOk()->json('data'))
        ->pluck('center_id')
        ->all();
    expect($confirmed)->toContain($org['centerA']->id)
        ->and($confirmed)->not->toContain($org['centerB']->id);

    $routes = collect($this->getJson('/api/director/route-tracking?active_only=1')->assertOk()->json('data'))
        ->pluck('employee_id')
        ->all();
    expect($routes)->toContain($org['empA']->id)
        ->and($routes)->not->toContain($org['empB']->id);

    Sanctum::actingAs($org['director']);
    $director = $this->getJson('/api/dashboard')->assertOk()->json('data');
    expect($director['active_centers'])->toBe(2)
        ->and($director['employees'])->toBe(4)
        ->and($director['confirmed_admissions'])->toBe(2);
});

it('lets a project head punch with camera attendance without joining assigned-center counts', function () {
    Storage::fake('public');
    $org = seedOrg();

    $this->actingAs($org['projectHead'], 'sanctum')
        ->post('/api/attendance/punch-in', [
            'latitude' => 18.52,
            'longitude' => 73.85,
            'location_address' => 'Pune',
            'photo' => UploadedFile::fake()->image('ph-in.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    $today = $this->actingAs($org['projectHead']->fresh(), 'sanctum')
        ->getJson('/api/attendance/today')
        ->assertOk();

    expect($today->json('data.attendance.punch_in_time'))->not->toBeNull()
        ->and($today->json('data.punch_in_allowed'))->toBeFalse();

    $dashboard = $this->actingAs($org['projectHead']->fresh(), 'sanctum')
        ->getJson('/api/dashboard')
        ->assertOk();

    expect($dashboard->json('data.punched_in_today'))->toBe(0);

    $employeeIds = collect(
        $this->actingAs($org['projectHead']->fresh(), 'sanctum')
            ->getJson('/api/director/employees')
            ->assertOk()
            ->json('data'),
    )->pluck('employee_id')->all();

    expect($employeeIds)->not->toContain($org['projectHead']->fresh()->employee_id);
});

it('never shows another project head assigned center to a project head', function () {
    $org = seedOrg();
    $otherPh = User::create([
        'name' => 'Other PH',
        'email' => 'otherph@test.local',
        'login_id' => 'otherph',
        'password' => Hash::make('ProjectHead@123'),
        'role' => \App\Enums\UserRole::ProjectHead->value,
        'is_active' => true,
    ]);
    $otherPh->headedCenters()->attach($org['centerB']->id);

    Attendance::create([
        'employee_id' => $org['empB']->id,
        'attendance_date' => AttendanceCalendar::today()->toDateString(),
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);

    Sanctum::actingAs($org['projectHead']);
    $this->getJson('/api/director/centers/'.$org['centerB']->id)->assertForbidden();
    $this->getJson('/api/director/employees?center_id='.$org['centerB']->id)->assertForbidden();
    $this->getJson('/api/director/team-attendance?center_id='.$org['centerB']->id)->assertForbidden();

    Sanctum::actingAs($otherPh);
    $centerIds = collect($this->getJson('/api/director/centers')->assertOk()->json('data'))
        ->pluck('id')
        ->all();
    expect($centerIds)->toContain($org['centerB']->id)
        ->and($centerIds)->not->toContain($org['centerA']->id);
});
