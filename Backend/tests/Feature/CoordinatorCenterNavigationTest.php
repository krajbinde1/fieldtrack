<?php

use App\Enums\AdmissionStatus;
use App\Filament\Pages\CenterDashboard;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Reports;
use App\Filament\Pages\SelectCenter;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Resources\Admissions\Pages\ListAdmissions;
use App\Filament\Resources\AdmissionTargets\AdmissionTargetResource;
use App\Filament\Resources\Attendances\AttendanceResource;
use App\Filament\Resources\Attendances\Pages\ListAttendances;
use App\Filament\Resources\Centers\CenterResource;
use App\Filament\Resources\Centers\Pages\ListCenters;
use App\Filament\Resources\EmployeeRoutes\EmployeeRouteResource;
use App\Filament\Resources\EmployeeRoutes\Pages\ListEmployeeRoutes;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Models\Admission;
use App\Models\Attendance;
use App\Models\User;
use App\Services\AdminCenterContext;
use App\Support\AttendanceCalendar;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function coordinatorSidebarLabels(): array
{
    app()->forgetInstance(\Filament\Navigation\NavigationManager::class);

    $labels = [];

    foreach (filament()->getNavigation() as $group) {
        foreach ($group->getItems() as $item) {
            $labels[] = $item->getLabel();
        }
    }

    return $labels;
}

function navigationCoordinator(array $org): User
{
    $coordinator = User::query()->create([
        'name' => 'Center Coordinator',
        'email' => 'coord-nav@test.local',
        'login_id' => 'coordnav',
        'password' => Hash::make('Coordinator@123'),
        'role' => \App\Enums\UserRole::CenterCoordinator->value,
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $coordinator->coordinatedCenters()->attach($org['centerA']->id);

    return $coordinator;
}

it('shows coordinators only three sidebar items and keeps other roles unchanged', function () {
    $org = seedOrg();
    $coordinator = navigationCoordinator($org);
    $removed = [
        'Attendances',
        'Employee Routes',
        'Admissions',
        'Admission Targets',
        'Leave Requests',
        'Reports',
    ];

    $this->actingAs($coordinator)
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('Center modules', false);

    expect(coordinatorSidebarLabels())->toEqualCanonicalizing([
        'Main Dashboard',
        'Center Dashboard',
        'Team Management',
    ])
        ->and(AttendanceResource::shouldRegisterNavigation())->toBeFalse()
        ->and(EmployeeRouteResource::shouldRegisterNavigation())->toBeFalse()
        ->and(AdmissionResource::shouldRegisterNavigation())->toBeFalse()
        ->and(AdmissionTargetResource::shouldRegisterNavigation())->toBeFalse()
        ->and(LeaveRequestResource::shouldRegisterNavigation())->toBeFalse()
        ->and(Reports::shouldRegisterNavigation())->toBeFalse()
        ->and(AttendanceResource::canAccess())->toBeTrue()
        ->and(Reports::canAccess())->toBeTrue()
        ->and(EmployeeResource::shouldRegisterNavigation())->toBeTrue()
        ->and(EmployeeResource::getNavigationLabel())->toBe('Team Management')
        ->and(CenterResource::shouldRegisterNavigation())->toBeTrue()
        ->and(CenterResource::getNavigationLabel())->toBe('Center Dashboard')
        ->and(CenterDashboard::shouldRegisterNavigation())->toBeFalse()
        ->and(Dashboard::getNavigationLabel())->toBe('Main Dashboard');

    $this->withSession([AdminCenterContext::SESSION_KEY => $org['centerA']->id]);

    $this->actingAs($coordinator)
        ->get('/admin/center-dashboard')
        ->assertOk()
        ->assertSee('ft-coord-modules', false)
        ->assertSee('Attendances')
        ->assertSee('Employee Routes')
        ->assertSee('Admissions')
        ->assertSee('Admission Targets')
        ->assertSee('Leave Requests')
        ->assertSee('Reports')
        ->assertSee('/admin/attendances', false)
        ->assertDontSee('Switch center', false)
        ->assertDontSee('Change Center', false)
        ->assertSee('Back to All Centers', false);

    expect(coordinatorSidebarLabels())->toEqualCanonicalizing([
        'Main Dashboard',
        'Center Dashboard',
        'Team Management',
    ]);

    $this->flushSession();
    $this->actingAs($org['admin'])->get('/admin')->assertOk();

    expect(array_values(array_intersect($removed, coordinatorSidebarLabels())))->toBe([])
        ->and(AdmissionResource::shouldRegisterNavigation())->toBeFalse()
        ->and(Reports::shouldRegisterNavigation())->toBeFalse();

    $this->flushSession();
    $this->withSession([AdminCenterContext::SESSION_KEY => $org['centerA']->id]);
    $this->actingAs($org['admin'])->get('/admin')->assertOk();

    expect(coordinatorSidebarLabels())->toContain(...$removed)
        ->and(coordinatorSidebarLabels())->toContain('Team Management', 'Center Dashboard')
        ->and(AdmissionResource::shouldRegisterNavigation())->toBeTrue()
        ->and(Reports::shouldRegisterNavigation())->toBeTrue()
        ->and(EmployeeResource::getNavigationLabel())->toBe('Users');

    $this->actingAs($org['admin'])
        ->get('/admin/attendances')
        ->assertOk()
        ->assertDontSee('Back to Center Dashboard', false);

    $this->actingAs($org['admin'])
        ->get('/admin/center-dashboard')
        ->assertOk()
        ->assertDontSee('Center modules', false)
        ->assertDontSee('Switch center', false);

    $this->flushSession();
    $this->actingAs($org['director'])->get('/admin')->assertOk();

    expect(coordinatorSidebarLabels())->toContain(...$removed)
        ->and(coordinatorSidebarLabels())->toContain('Team Management')
        ->and(Dashboard::getNavigationLabel())->toBe('Dashboard')
        ->and(Reports::shouldRegisterNavigation())->toBeTrue()
        ->and(CenterDashboard::canAccess())->toBeFalse();

    $this->flushSession();
    $this->actingAs($org['centerManager'])->get('/admin')->assertOk();

    expect(coordinatorSidebarLabels())->toContain(...$removed)
        ->and(coordinatorSidebarLabels())->toContain('Users', 'Center Dashboard', 'Dashboard')
        ->and(coordinatorSidebarLabels())->not->toContain('Team Management')
        ->and(EmployeeResource::getNavigationLabel())->toBe('Users')
        ->and(EmployeeResource::shouldRegisterNavigation())->toBeTrue()
        ->and(Reports::shouldRegisterNavigation())->toBeTrue()
        ->and(Dashboard::getNavigationLabel())->toBe('Dashboard');

    $this->actingAs($org['centerManager'])
        ->get('/admin/attendances')
        ->assertOk()
        ->assertDontSee('Back to Center Dashboard', false);

    $this->actingAs($org['centerManager'])
        ->post(route('admin.clear-center'))
        ->assertForbidden();
});

it('scopes coordinator modules to the selected center and can switch assigned centers', function () {
    $org = seedOrg();
    $coordinator = navigationCoordinator($org);
    $today = AttendanceCalendar::today()->toDateString();

    $attendanceA = Attendance::query()->create([
        'employee_id' => $org['empA']->id,
        'attendance_date' => $today,
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);
    $attendanceB = Attendance::query()->create([
        'employee_id' => $org['empB']->id,
        'attendance_date' => $today,
        'punch_in_time' => '09:30:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);
    $admissionA = Admission::query()->create([
        'scheme_id' => $org['projectA']->id,
        'center_id' => $org['centerA']->id,
        'employee_id' => $org['empA']->id,
        'first_name' => 'Asha',
        'last_name' => 'One',
        'full_name' => 'Asha One',
        'status' => AdmissionStatus::Submitted,
    ]);
    $admissionB = Admission::query()->create([
        'scheme_id' => $org['projectB']->id,
        'center_id' => $org['centerB']->id,
        'employee_id' => $org['empB']->id,
        'first_name' => 'Bina',
        'last_name' => 'Two',
        'full_name' => 'Bina Two',
        'status' => AdmissionStatus::Submitted,
    ]);

    expect(fn () => app(AdminCenterContext::class)->select($coordinator, $org['centerB']->id))
        ->toThrow(ValidationException::class);

    $coordinator->coordinatedCenters()->attach($org['centerB']->id);

    Livewire::actingAs($coordinator)
        ->test(ListEmployees::class)
        ->assertCanSeeTableRecords([$org['empA'], $org['empB']]);

    Livewire::actingAs($coordinator)
        ->test(ListAttendances::class)
        ->assertCanNotSeeTableRecords([$attendanceA, $attendanceB]);

    $this->actingAs($coordinator)->get('/admin/attendances')->assertRedirect('/admin/centers');
    $this->actingAs($coordinator)->get('/admin/employee-routes')->assertRedirect('/admin/centers');
    $this->actingAs($coordinator)->get('/admin/admissions')->assertRedirect('/admin/centers');
    $this->actingAs($coordinator)->get('/admin/admission-targets')->assertRedirect('/admin/centers');
    $this->actingAs($coordinator)->get('/admin/leave-requests')->assertRedirect('/admin/centers');
    $this->actingAs($coordinator)->get('/admin/reports')->assertRedirect('/admin/centers');
    $this->actingAs($coordinator)->get('/admin/center-dashboard')->assertRedirect('/admin/centers');
    $this->actingAs($coordinator)->get('/admin/center-users')->assertOk();

    Livewire::actingAs($coordinator)
        ->test(ListCenters::class)
        ->callAction(TestAction::make('openDashboard')->table($org['centerA']))
        ->assertRedirect('/admin/center-dashboard');

    expect(session(AdminCenterContext::SESSION_KEY))->toBe($org['centerA']->id);

    $this->withSession([AdminCenterContext::SESSION_KEY => $org['centerA']->id]);

    $dashboard = $this->actingAs($coordinator)
        ->get('/admin/center-dashboard')
        ->assertOk()
        ->assertSee('ft-coord-modules', false)
        ->assertSee('Switch center', false)
        ->assertSee('Change Center', false)
        ->assertSee('Back to All Centers', false)
        ->assertDontSee('Back to Center Dashboard', false);

    foreach ([
        AttendanceResource::getUrl(),
        EmployeeRouteResource::getUrl(),
        AdmissionResource::getUrl(),
        AdmissionTargetResource::getUrl(),
        LeaveRequestResource::getUrl(),
        Reports::getUrl(),
    ] as $url) {
        $dashboard->assertSee($url, false);
    }

    $this->actingAs($coordinator)
        ->get('/admin/attendances')
        ->assertOk()
        ->assertSee('Back to Center Dashboard', false)
        ->assertSee(CenterDashboard::getUrl(), false);

    $this->actingAs($coordinator)
        ->get('/admin/reports')
        ->assertOk()
        ->assertSee('Back to Center Dashboard', false);

    Livewire::actingAs($coordinator)
        ->test(ListAttendances::class)
        ->assertCanSeeTableRecords([$attendanceA])
        ->assertCanNotSeeTableRecords([$attendanceB]);

    Livewire::actingAs($coordinator)
        ->test(ListEmployeeRoutes::class)
        ->assertCanSeeTableRecords([$attendanceA])
        ->assertCanNotSeeTableRecords([$attendanceB]);

    Livewire::actingAs($coordinator)
        ->test(ListAdmissions::class)
        ->assertCanSeeTableRecords([$admissionA])
        ->assertCanNotSeeTableRecords([$admissionB]);

    Livewire::actingAs($coordinator)
        ->test(Reports::class)
        ->assertCanSeeTableRecords([$attendanceA])
        ->assertCanNotSeeTableRecords([$attendanceB]);

    expect(SelectCenter::canAccess())->toBeTrue();

    Livewire::actingAs($coordinator)
        ->test(SelectCenter::class)
        ->fillForm(['center_id' => $org['centerB']->id])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertRedirect('/admin/center-dashboard');

    expect(session(AdminCenterContext::SESSION_KEY))->toBe($org['centerB']->id);

    $this->withSession([AdminCenterContext::SESSION_KEY => $org['centerB']->id]);

    Livewire::actingAs($coordinator)
        ->test(ListAttendances::class)
        ->assertCanSeeTableRecords([$attendanceB])
        ->assertCanNotSeeTableRecords([$attendanceA]);

    Livewire::actingAs($coordinator)
        ->test(ListAdmissions::class)
        ->assertCanSeeTableRecords([$admissionB])
        ->assertCanNotSeeTableRecords([$admissionA]);

    Livewire::actingAs($coordinator)
        ->test(Reports::class)
        ->assertCanSeeTableRecords([$attendanceB])
        ->assertCanNotSeeTableRecords([$attendanceA]);

    $this->actingAs($coordinator)
        ->post(route('admin.clear-center'))
        ->assertRedirect('/admin/centers')
        ->assertSessionMissing(AdminCenterContext::SESSION_KEY);

    $this->actingAs($coordinator)->get('/admin/attendances')->assertRedirect('/admin/centers');

    Livewire::actingAs($coordinator)
        ->test(ListEmployees::class)
        ->assertCanSeeTableRecords([$org['empA'], $org['empB']]);

    Livewire::actingAs($coordinator)
        ->test(ListAttendances::class)
        ->assertCanNotSeeTableRecords([$attendanceA, $attendanceB]);
});
