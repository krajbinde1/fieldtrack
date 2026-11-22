<?php

use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\OrgUsers\OrgUserResource;
use App\Filament\Resources\OrgUsers\Pages\EditOrgUser;
use App\Models\Attendance;
use App\Models\User;
use App\Support\AttendanceCalendar;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

function hierarchyCoordinator(array $org): User
{
    $coordinator = User::query()->create([
        'name' => 'Coordinator',
        'email' => 'coord-hierarchy@test.local',
        'login_id' => 'coordhierarchy',
        'password' => Hash::make('Coordinator@123'),
        'role' => \App\Enums\UserRole::CenterCoordinator->value,
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $coordinator->coordinatedCenters()->attach($org['centerA']->id);

    return $coordinator;
}

it('shows assigned centers, managers, and employees in the hierarchy', function () {
    $org = seedOrg();
    $coordinator = hierarchyCoordinator($org);
    $org['centerManager']->update(['login_id' => '9876543210']);
    $managerEdit = OrgUserResource::getUrl('edit', ['record' => $org['centerManager']]);
    $employeeEdit = EmployeeResource::getUrl('edit', ['record' => $org['empA']]);

    Livewire::actingAs($coordinator)
        ->test(ListEmployees::class)
        ->assertSet('teamLayout', 'list')
        ->assertCanSeeTableRecords([$org['empA']])
        ->assertCanNotSeeTableRecords([$org['empB']])
        ->call('setLayout', 'hierarchy')
        ->assertSet('teamLayout', 'hierarchy')
        ->assertSee('Center A')
        ->assertSee('CM')
        ->assertSee('Emp A')
        ->assertSee('Center Manager')
        ->assertDontSee('Center B')
        ->assertDontSee('Emp B')
        ->assertSee($managerEdit)
        ->assertSee($employeeEdit)
        ->assertSee('Reset Password')
        ->assertSee('Deactivate')
        ->call('setLayout', 'list')
        ->assertCanSeeTableRecords([$org['empA']]);

    $this->actingAs($coordinator)
        ->get($employeeEdit)
        ->assertOk()
        ->assertSee('Emp A');

    Livewire::actingAs($coordinator)
        ->test(EditOrgUser::class, ['record' => $org['centerManager']->getKey()])
        ->assertOk();
});

it('resets a center manager password to the last four mobile digits and toggles access', function () {
    $org = seedOrg();
    $coordinator = hierarchyCoordinator($org);
    $org['centerManager']->update(['login_id' => '9876543210']);

    Livewire::actingAs($coordinator)
        ->test(ListEmployees::class)
        ->call('setLayout', 'hierarchy')
        ->call('resetHierarchyPassword', 'manager', $org['centerManager']->id)
        ->assertNotified('Password reset successfully. Default password: 3210')
        ->call('toggleHierarchyActive', 'manager', $org['centerManager']->id);

    $manager = $org['centerManager']->fresh();
    expect(Hash::check('3210', $manager->password))->toBeTrue()
        ->and($manager->must_change_password)->toBeTrue()
        ->and($manager->is_active)->toBeFalse();

    Livewire::actingAs($coordinator)
        ->test(ListEmployees::class)
        ->call('resetHierarchyPassword', 'manager', $org['director']->id)
        ->assertForbidden();
});

it('resets, deactivates, and deletes an employee only when nothing references them', function () {
    $org = seedOrg();
    $coordinator = hierarchyCoordinator($org);

    Livewire::actingAs($coordinator)
        ->test(ListEmployees::class)
        ->call('setLayout', 'hierarchy')
        ->call('resetHierarchyPassword', 'employee', $org['empA']->id)
        ->assertNotified('Password reset successfully. Default password: 0001')
        ->call('toggleHierarchyActive', 'employee', $org['empA']->id);

    expect($org['empA']->fresh()->status)->toBeFalse()
        ->and($org['userA']->fresh()->is_active)->toBeFalse()
        ->and(Hash::check('0001', $org['userA']->fresh()->password))->toBeTrue();

    Attendance::query()->create([
        'employee_id' => $org['empA']->id,
        'attendance_date' => AttendanceCalendar::today()->toDateString(),
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);

    Livewire::actingAs($coordinator)
        ->test(ListEmployees::class)
        ->call('deleteHierarchyUser', 'employee', $org['empA']->id)
        ->assertNotified('Cannot delete this user because related records exist.');

    expect($org['empA']->fresh())->not->toBeNull()
        ->and($org['userA']->fresh())->not->toBeNull();

    $spare = \App\Models\Employee::query()->create([
        'center_id' => $org['centerA']->id,
        'full_name' => 'Spare Emp',
        'mobile' => '9000000099',
        'status' => true,
    ]);
    $spareUser = User::query()->create([
        'employee_id' => $spare->id,
        'name' => 'Spare Emp',
        'email' => 'spare@test.local',
        'login_id' => '9000000099',
        'password' => Hash::make('Employee@123'),
        'role' => \App\Enums\UserRole::Employee->value,
        'is_active' => true,
    ]);

    Livewire::actingAs($coordinator)
        ->test(ListEmployees::class)
        ->call('deleteHierarchyUser', 'employee', $spare->id)
        ->assertNotified('User deleted.');

    expect($spare->fresh())->toBeNull()
        ->and($spareUser->fresh())->toBeNull()
        ->and($org['empA']->fresh())->not->toBeNull();

    Livewire::actingAs($coordinator)
        ->test(ListEmployees::class)
        ->call('deleteHierarchyUser', 'manager', $org['centerManager']->id)
        ->assertForbidden();
});

it('hides other centers and higher-role actions from a center manager', function () {
    $org = seedOrg();

    Livewire::actingAs($org['centerManager'])
        ->test(ListEmployees::class)
        ->call('setLayout', 'hierarchy')
        ->assertSee('Center A')
        ->assertSee('Emp A')
        ->assertDontSee('Emp B')
        ->assertDontSee(OrgUserResource::getUrl('edit', ['record' => $org['centerManager']]))
        ->call('resetHierarchyPassword', 'manager', $org['centerManager']->id)
        ->assertForbidden();

    Livewire::actingAs($org['centerManager'])
        ->test(EditEmployee::class, ['record' => $org['empA']->getKey()])
        ->assertOk();
});
