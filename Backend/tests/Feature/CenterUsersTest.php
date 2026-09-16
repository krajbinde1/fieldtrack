<?php

use App\Enums\CenterStaffRole;
use App\Enums\UserRole;
use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\OrgUsers\Pages\CreateOrgUser;
use App\Models\Employee;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
});

it('lets a center manager create a user only for their own center', function () {
    $manager = User::query()->where('login_id', 'centermgr')->firstOrFail();
    $centerId = $manager->managedCenters()->first()?->id;
    expect($centerId)->not->toBeNull();

    Livewire::actingAs($manager)
        ->test(CreateEmployee::class)
        ->fillForm([
            'center_id' => $centerId,
            'full_name' => 'New Mobilizer',
            'mobile' => '9123456780',
            'email' => 'mobilizer@fieldtrack.local',
            'login_id' => 'field.mob-1',
            'login_password' => 'Mobilizer@123',
            'staff_role' => CenterStaffRole::Mobilizer->value,
            'status' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $employee = Employee::query()->where('mobile', '9123456780')->first();
    expect($employee)->not->toBeNull()
        ->and($employee->center_id)->toBe($centerId)
        ->and($employee->staff_role)->toBe(CenterStaffRole::Mobilizer->value)
        ->and($employee->created_by_user_id)->toBe($manager->id)
        ->and($employee->user?->login_id)->toBe('field.mob-1')
        ->and($employee->user?->role)->toBe(UserRole::Employee->value);

    $this->postJson('/api/login', [
        'login_id' => 'field.mob-1',
        'password' => 'Mobilizer@123',
        'device_id' => 'device-staff',
    ])->assertOk()
        ->assertJsonPath('employee.staff_role', CenterStaffRole::Mobilizer->value)
        ->assertJsonPath('user.role', UserRole::Employee->value);
});

it('uses mobile as login id when center user login id is blank', function () {
    $manager = User::query()->where('login_id', 'centermgr')->firstOrFail();
    $centerId = $manager->managedCenters()->first()?->id;

    Livewire::actingAs($manager)
        ->test(CreateEmployee::class)
        ->fillForm([
            'center_id' => $centerId,
            'full_name' => 'Housekeeper One',
            'mobile' => '9123456781',
            'email' => null,
            'login_id' => null,
            'login_password' => 'House@1234',
            'staff_role' => CenterStaffRole::Housekeeper->value,
            'status' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('login_id', '9123456781')->exists())->toBeTrue();
});

it('does not let a center manager see or edit another center user', function () {
    $manager = User::query()->where('login_id', 'centermgr')->firstOrFail();
    $own = Employee::query()->where('mobile', '9876543210')->firstOrFail();
    $other = Employee::query()->where('mobile', '9876543211')->firstOrFail();

    Livewire::actingAs($manager)
        ->test(\App\Filament\Resources\Employees\Pages\ListEmployees::class)
        ->assertCanSeeTableRecords([$own])
        ->assertCanNotSeeTableRecords([$other]);

    $this->actingAs($manager)
        ->get('/admin/center-users/'.$other->id.'/edit')
        ->assertNotFound();
});

it('does not let admin create operational center staff', function () {
    $admin = User::query()->where('login_id', 'director')->firstOrFail();

    $this->actingAs($admin)->get('/admin/center-users')->assertForbidden();
    $this->actingAs($admin)->get('/admin/center-users/create')->assertForbidden();
});

it('lets admin create a director from the people users module', function () {
    $admin = User::query()->where('login_id', 'director')->firstOrFail();
    $projectId = \App\Models\Project::query()->where('code', 'DEMO')->value('id');

    Livewire::actingAs($admin)
        ->test(CreateOrgUser::class)
        ->fillForm([
            'role' => UserRole::Director->value,
            'name' => 'Second Director',
            'mobile' => '9000000099',
            'login_id' => 'director2',
            'email' => 'director2@fieldtrack.local',
            'password' => 'Director@123',
            'directedProjects' => [$projectId],
            'is_active' => true,
            'must_change_password' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = User::query()->where('login_id', 'director2')->first();
    expect($created)->not->toBeNull()
        ->and($created->role)->toBe(UserRole::Director->value)
        ->and($created->directedProjects()->pluck('projects.id')->all())->toContain($projectId);
});
