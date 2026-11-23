<?php

use App\Enums\UserRole;
use App\Filament\Widgets\DirectorAdminStatsWidget;
use App\Models\Attendance;
use App\Models\Center;
use App\Models\Employee;
use App\Models\User;
use App\Support\AttendanceCalendar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

it('counts unique active center employees and matches the punched-in list for the same IST day', function () {
    $org = seedOrg();
    $today = AttendanceCalendar::today()->toDateString();
    $yesterday = AttendanceCalendar::today()->subDay()->toDateString();

    $coordinator = User::query()->create([
        'name' => 'Coordinator',
        'email' => 'coord-attendance@test.local',
        'login_id' => 'coordattendance',
        'password' => Hash::make('Coordinator@123'),
        'role' => UserRole::CenterCoordinator->value,
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $coordinator->coordinatedCenters()->attach([$org['centerA']->id, $org['centerB']->id]);

    $shared = Employee::query()->create([
        'center_id' => $org['centerA']->id,
        'full_name' => 'Shared Staff',
        'mobile' => '9222222222',
        'status' => true,
        'staff_role' => 'trainer',
    ]);
    User::query()->create([
        'employee_id' => $shared->id,
        'name' => 'Shared Staff',
        'email' => 'shared@test.local',
        'login_id' => '9222222222',
        'password' => Hash::make('Employee@123'),
        'role' => UserRole::Employee->value,
        'is_active' => true,
    ]);
    $shared->assignCenter($org['centerA']->id);
    $shared->assignCenter($org['centerB']->id);

    $punchedOut = Employee::query()->create([
        'center_id' => $org['centerA']->id,
        'full_name' => 'Punched Out Staff',
        'mobile' => '9333333333',
        'status' => true,
        'staff_role' => 'mobilizer',
    ]);
    User::query()->create([
        'employee_id' => $punchedOut->id,
        'name' => 'Punched Out Staff',
        'email' => 'punchedout@test.local',
        'login_id' => '9333333333',
        'password' => Hash::make('Employee@123'),
        'role' => UserRole::Employee->value,
        'is_active' => true,
    ]);

    $yesterdayEmployee = Employee::query()->create([
        'center_id' => $org['centerA']->id,
        'full_name' => 'Yesterday Staff',
        'mobile' => '9444444444',
        'status' => true,
        'staff_role' => 'mis',
    ]);
    User::query()->create([
        'employee_id' => $yesterdayEmployee->id,
        'name' => 'Yesterday Staff',
        'email' => 'yesterday@test.local',
        'login_id' => '9444444444',
        'password' => Hash::make('Employee@123'),
        'role' => UserRole::Employee->value,
        'is_active' => true,
    ]);

    $noPunch = Employee::query()->create([
        'center_id' => $org['centerA']->id,
        'full_name' => 'No Punch Staff',
        'mobile' => '9555555555',
        'status' => true,
        'staff_role' => 'trainer',
    ]);
    User::query()->create([
        'employee_id' => $noPunch->id,
        'name' => 'No Punch Staff',
        'email' => 'nopunch@test.local',
        'login_id' => '9555555555',
        'password' => Hash::make('Employee@123'),
        'role' => UserRole::Employee->value,
        'is_active' => true,
    ]);

    Employee::query()->create([
        'center_id' => $org['centerA']->id,
        'full_name' => 'Inactive Staff',
        'mobile' => '9666666666',
        'status' => false,
        'staff_role' => 'trainer',
    ]);

    $managerEmployee = Employee::query()->create([
        'center_id' => $org['centerA']->id,
        'full_name' => 'CM',
        'mobile' => '9777777777',
        'status' => true,
        'designation' => 'Center Manager',
    ]);
    $org['centerManager']->update(['employee_id' => $managerEmployee->id]);
    $managerEmployee->assignCenter($org['centerA']->id);

    $outsideCenter = Center::query()->create([
        'scheme_id' => $org['projectB']->id,
        'name' => 'Outside Center',
        'code' => 'OC',
        'is_active' => true,
    ]);
    $outside = Employee::query()->create([
        'center_id' => $outsideCenter->id,
        'full_name' => 'Outside Staff',
        'mobile' => '9888888888',
        'status' => true,
        'staff_role' => 'mobilizer',
    ]);
    User::query()->create([
        'employee_id' => $outside->id,
        'name' => 'Outside Staff',
        'email' => 'outside@test.local',
        'login_id' => '9888888888',
        'password' => Hash::make('Employee@123'),
        'role' => UserRole::Employee->value,
        'is_active' => true,
    ]);

    Attendance::query()->create([
        'employee_id' => $org['empA']->id,
        'attendance_date' => $today,
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);
    Attendance::query()->create([
        'employee_id' => $punchedOut->id,
        'attendance_date' => $today,
        'punch_in_time' => '09:05:00',
        'punch_out_time' => '18:00:00',
        'attendance_status' => 'Punched Out',
        'approval_status' => 'Pending',
    ]);
    Attendance::query()->create([
        'employee_id' => $yesterdayEmployee->id,
        'attendance_date' => $yesterday,
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);
    Attendance::query()->create([
        'employee_id' => $noPunch->id,
        'attendance_date' => $today,
        'punch_in_time' => null,
        'attendance_status' => 'Absent',
        'approval_status' => 'Pending',
    ]);
    Attendance::query()->create([
        'employee_id' => $managerEmployee->id,
        'attendance_date' => $today,
        'punch_in_time' => '08:30:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);
    Attendance::query()->create([
        'employee_id' => $outside->id,
        'attendance_date' => $today,
        'punch_in_time' => '08:45:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);

    Sanctum::actingAs($coordinator);

    $dashboard = $this->getJson('/api/dashboard')->assertOk()->json('data');
    $list = collect($this->getJson('/api/director/employees')->assertOk()->json('data'));
    $punchedIds = coordinatorDashboardPunchedIds($list);

    expect($dashboard['today'])->toBe($today)
        ->and($dashboard['employees'])->toBe(6)
        ->and($list)->toHaveCount(6)
        ->and($list->pluck('employee_id')->unique())->toHaveCount(6)
        ->and($dashboard['punched_in_today'])->toBe(2)
        ->and($punchedIds)->toBe([$org['empA']->id, $punchedOut->id])
        ->and($list->firstWhere('employee_id', $org['empA']->id)['attendance_status'])->toBe('punched_in')
        ->and($list->firstWhere('employee_id', $punchedOut->id)['attendance_status'])->toBe('punched_out')
        ->and($list->firstWhere('employee_id', $yesterdayEmployee->id)['attendance_status'])->toBe('not_punched_in')
        ->and($list->firstWhere('employee_id', $noPunch->id)['attendance_status'])->toBe('not_punched_in')
        ->and($list->pluck('employee_id')->all())->not->toContain($managerEmployee->id)
        ->and($list->pluck('employee_id')->all())->not->toContain($outside->id);

    $centerA = $this->getJson('/api/dashboard?center_id='.$org['centerA']->id)->assertOk()->json('data');
    $centerAList = collect($this->getJson('/api/director/employees?center_id='.$org['centerA']->id)->assertOk()->json('data'));

    expect($centerA['employees'])->toBe(5)
        ->and($centerAList)->toHaveCount(5)
        ->and($centerA['punched_in_today'])->toBe(count(coordinatorDashboardPunchedIds($centerAList)))
        ->and(coordinatorDashboardPunchedIds($centerAList))->toBe([$org['empA']->id, $punchedOut->id])
        ->and($centerAList->pluck('employee_id')->all())->toContain($shared->id)
        ->and($centerAList->pluck('employee_id')->all())->not->toContain($org['empB']->id);

    $centerB = $this->getJson('/api/dashboard?center_id='.$org['centerB']->id)->assertOk()->json('data');
    $centerBList = collect($this->getJson('/api/director/employees?center_id='.$org['centerB']->id)->assertOk()->json('data'));

    expect($centerB['employees'])->toBe(2)
        ->and($centerBList)->toHaveCount(2)
        ->and($centerB['punched_in_today'])->toBe(0)
        ->and(coordinatorDashboardPunchedIds($centerBList))->toBe([])
        ->and($centerBList->pluck('employee_id')->all())->toContain($shared->id)
        ->and($centerBList->pluck('employee_id')->all())->toContain($org['empB']->id);

    $cards = Livewire::actingAs($coordinator)
        ->test(DirectorAdminStatsWidget::class, ['organizationWide' => true])
        ->instance()
        ->getCards();
    $byLabel = collect($cards)->keyBy('label');

    expect($byLabel['Total Employees']['value'])->toBe('6')
        ->and($byLabel['Punched In Today']['value'])->toBe('2 / 6');
});

/**
 * @param  Collection<int, array<string, mixed>>  $rows
 * @return list<int>
 */
function coordinatorDashboardPunchedIds(Collection $rows): array
{
    return $rows
        ->filter(fn (array $row): bool => in_array($row['attendance_status'], ['punched_in', 'punched_out'], true))
        ->pluck('employee_id')
        ->map(fn ($id): int => (int) $id)
        ->sort()
        ->values()
        ->all();
}
