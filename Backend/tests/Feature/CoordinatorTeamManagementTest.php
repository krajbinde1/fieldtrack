<?php

use App\Enums\CenterStaffRole;
use App\Enums\UserRole;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\OrgUsers\OrgUserResource;
use App\Filament\Widgets\DirectorAdminStatsWidget;
use App\Models\Attendance;
use App\Models\User;
use App\Support\AttendanceCalendar;
use Filament\Navigation\NavigationManager;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

function coordinatorPeopleLabels(): array
{
    app()->forgetInstance(NavigationManager::class);

    $labels = [];

    foreach (filament()->getNavigation() as $group) {
        if ($group->getLabel() !== 'People') {
            continue;
        }

        foreach ($group->getItems() as $item) {
            $labels[] = $item->getLabel();
        }
    }

    return $labels;
}

function coordinatorForCenterA(array $org): User
{
    $coordinator = User::query()->create([
        'name' => 'Coordinator',
        'email' => 'coord-team@test.local',
        'login_id' => 'coordteam',
        'password' => Hash::make('Coordinator@123'),
        'role' => UserRole::CenterCoordinator->value,
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $coordinator->coordinatedCenters()->attach($org['centerA']->id);

    return $coordinator;
}

it('shows coordinators only Team Management and scopes the existing user list', function () {
    $org = seedOrg();
    $coordinator = coordinatorForCenterA($org);

    $this->actingAs($coordinator)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Team Management')
        ->assertSee('/admin/center-users');

    expect(coordinatorPeopleLabels())->toBe(['Team Management'])
        ->and(EmployeeResource::getNavigationLabel())->toBe('Team Management')
        ->and(EmployeeResource::getPluralModelLabel())->toBe('Team Management')
        ->and(EmployeeResource::shouldRegisterNavigation())->toBeTrue()
        ->and(OrgUserResource::canAccess())->toBeTrue()
        ->and(OrgUserResource::shouldRegisterNavigation())->toBeFalse()
        ->and(EmployeeResource::getUrl())->toContain('/admin/center-users');

    Livewire::actingAs($coordinator)
        ->test(DirectorAdminStatsWidget::class)
        ->assertSee('/admin/center-users')
        ->assertDontSee('/admin/users');

    Livewire::actingAs($coordinator)
        ->test(ListEmployees::class)
        ->assertSee('Team Management')
        ->assertDontSee('Users')
        ->assertActionVisible('create')
        ->assertSee('New User')
        ->assertCanSeeTableRecords([$org['empA']])
        ->assertCanNotSeeTableRecords([$org['empB']])
        ->assertTableActionVisible('edit', $org['empA'])
        ->assertTableActionVisible('resetPassword', $org['empA'])
        ->assertTableActionVisible('delete', $org['empA']);

    Livewire::actingAs($coordinator)
        ->test(CreateEmployee::class)
        ->fillForm([
            'center_id' => $org['centerA']->id,
            'full_name' => 'Coordinator Hire',
            'mobile' => '9123456701',
            'staff_role' => CenterStaffRole::Mobilizer->value,
            'travel_km_rate' => 5,
            'monthly_km_limit' => 300,
            'status' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::actingAs($coordinator)
        ->test(EditEmployee::class, ['record' => $org['empA']->getKey()])
        ->assertActionExists('resetPassword')
        ->callAction('resetPassword')
        ->assertNotified('Password reset successfully. Default password: 0001');

    $this->actingAs($coordinator)
        ->get('/admin/center-users/'.$org['empB']->id.'/edit')
        ->assertNotFound();
});

it('blocks a coordinator from deleting a user who has related records', function () {
    $org = seedOrg();
    $coordinator = coordinatorForCenterA($org);
    $attendance = Attendance::query()->create([
        'employee_id' => $org['empA']->id,
        'attendance_date' => AttendanceCalendar::today()->toDateString(),
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);

    $this->actingAs($coordinator);
    expect(EmployeeResource::canDelete($org['empA']))->toBeTrue()
        ->and(EmployeeResource::canDelete($org['empB']))->toBeFalse();

    Livewire::actingAs($coordinator)
        ->test(ListEmployees::class)
        ->callTableAction('delete', $org['empA'])
        ->assertNotified('Cannot delete this user because related records exist.')
        ->assertCanSeeTableRecords([$org['empA']]);

    expect($org['empA']->fresh())->not->toBeNull()
        ->and($org['userA']->fresh())->not->toBeNull()
        ->and(Attendance::query()->find($attendance->id))->not->toBeNull()
        ->and(Attendance::query()->find($attendance->id)?->employee_id)->toBe($org['empA']->id)
        ->and($org['empB']->fresh())->not->toBeNull()
        ->and($coordinator->fresh())->not->toBeNull();
});

it('keeps admin and center manager people menus unchanged', function () {
    $org = seedOrg();

    $this->actingAs($org['admin'])->get('/admin')->assertOk();

    expect(coordinatorPeopleLabels())->toBe(['Team Management'])
        ->and(OrgUserResource::getNavigationLabel())->toBe('Team Management')
        ->and(OrgUserResource::shouldRegisterNavigation())->toBeTrue()
        ->and(EmployeeResource::canAccess())->toBeFalse()
        ->and(EmployeeResource::getNavigationLabel())->toBe('Users');

    $this->flushSession();
    $this->actingAs($org['director'])->get('/admin')->assertOk();

    expect(coordinatorPeopleLabels())->toBe(['Team Management'])
        ->and(OrgUserResource::shouldRegisterNavigation())->toBeTrue()
        ->and(EmployeeResource::canAccess())->toBeFalse();

    $this->flushSession();
    app()->forgetInstance(NavigationManager::class);
    $this->actingAs($org['centerManager'])
        ->get('/admin')
        ->assertOk()
        ->assertSee('Users')
        ->assertDontSee('Team Management');

    expect(coordinatorPeopleLabels())->toBe(['Users'])
        ->and(EmployeeResource::getNavigationLabel())->toBe('Users')
        ->and(EmployeeResource::shouldRegisterNavigation())->toBeTrue()
        ->and(OrgUserResource::shouldRegisterNavigation())->toBeFalse();
});
