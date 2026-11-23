<?php

use App\Enums\AdmissionStatus;
use App\Enums\UserRole;
use App\Filament\Pages\AppUpdateSettings;
use App\Filament\Pages\CenterDashboard;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\DeviceManagement;
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
use App\Filament\Resources\ExpenseClaims\ExpenseClaimResource;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Filament\Resources\OrgUsers\OrgUserResource;
use App\Models\Admission;
use App\Models\Attendance;
use App\Models\User;
use App\Services\AdminCenterContext;
use App\Support\AttendanceCalendar;
use Filament\Actions\Testing\TestAction;
use Filament\Navigation\NavigationManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function resetFilamentNavigationRequest(): void
{
    app()->forgetInstance(NavigationManager::class);
    app()->forgetInstance('originalRequest');
}

function coordinatorSidebarLabels(): array
{
    app()->forgetInstance(NavigationManager::class);

    $labels = [];

    foreach (filament()->getNavigation() as $group) {
        foreach ($group->getItems() as $item) {
            $labels[] = $item->getLabel();
        }
    }

    $user = auth()->user();

    if ($user instanceof User && $user->isAdmin()) {
        $labels[] = Dashboard::getNavigationLabel();
    }

    return $labels;
}

function centerSidebarLabels(): array
{
    return [
        'Main Dashboard',
        'Center Dashboard',
        'Attendances',
        'Employee Routes',
        'Team Activity',
        'Admissions',
        'Admission Targets',
        'Leave Requests',
        'Reports',
        'Expenses',
    ];
}

function mainSidebarLabels(): array
{
    return [
        'Main Dashboard',
        'Center Dashboard',
        'Team Management',
        'Expenses',
    ];
}

function adminMainSidebarLabels(): array
{
    return [
        ...mainSidebarLabels(),
        'App Update Settings',
        'Device Management',
    ];
}

function adminCenterSidebarLabels(): array
{
    return [
        ...centerSidebarLabels(),
        'App Update Settings',
        'Device Management',
    ];
}

/**
 * @return array{group: ?string, collapsible: bool, url: string}|null
 */
function expenseSidebarLink(): ?array
{
    app()->forgetInstance(NavigationManager::class);

    foreach (filament()->getNavigation() as $group) {
        foreach ($group->getItems() as $item) {
            if ($item->getLabel() !== 'Expenses') {
                continue;
            }

            return [
                'group' => $group->getLabel(),
                'collapsible' => $group->isCollapsible(),
                'url' => $item->getUrl(),
            ];
        }
    }

    return null;
}

function assertExpensesSidebar(\Illuminate\Testing\TestResponse $response): void
{
    $link = expenseSidebarLink();

    expect($link)->not->toBeNull()
        ->and($link['group'])->toBe('Expenses')
        ->and($link['collapsible'])->toBeFalse()
        ->and($link['url'])->toContain('/admin/expense-claims');

    $html = $response->getContent();

    expect($html)->toMatch('/<nav class="fi-sidebar-nav">[\s\S]*data-group-label="Expenses"[\s\S]*href="[^"]*\/admin\/expense-claims"[\s\S]*<\/nav>/');

    preg_match('/<li\b[^>]*data-group-label="Expenses"[^>]*>/', $html, $group);

    expect($group[0] ?? '')->not->toContain('fi-collapsible')
        ->and($group[0] ?? '')->toContain('fi-sidebar-group');
}

function navigationCoordinator(array $org): User
{
    $coordinator = User::query()->create([
        'name' => 'Center Coordinator',
        'email' => 'coord-nav@test.local',
        'login_id' => 'coordnav',
        'password' => Hash::make('Coordinator@123'),
        'role' => UserRole::CenterCoordinator->value,
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $coordinator->coordinatedCenters()->attach($org['centerA']->id);

    return $coordinator;
}

it('switches the shared sidebar when a center is opened and restores it afterwards', function () {
    $org = seedOrg();
    $coordinator = navigationCoordinator($org);
    $removed = [
        'Attendances',
        'Employee Routes',
        'Team Activity',
        'Admissions',
        'Admission Targets',
        'Leave Requests',
        'Reports',
    ];

    $this->actingAs($coordinator)
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('Center modules', false)
        ->assertDontSee('ft-coord-modules', false);

    expect(coordinatorSidebarLabels())->toEqualCanonicalizing(mainSidebarLabels())
        ->and(AppUpdateSettings::shouldRegisterNavigation())->toBeFalse()
        ->and(DeviceManagement::shouldRegisterNavigation())->toBeFalse()
        ->and(AttendanceResource::canAccess())->toBeTrue()
        ->and(Reports::canAccess())->toBeTrue()
        ->and(EmployeeResource::shouldRegisterNavigation())->toBeTrue()
        ->and(EmployeeResource::getNavigationLabel())->toBe('Team Management')
        ->and(CenterResource::shouldRegisterNavigation())->toBeTrue()
        ->and(CenterResource::getNavigationLabel())->toBe('Center Dashboard')
        ->and(CenterDashboard::canAccess())->toBeTrue()
        ->and(Dashboard::getNavigationLabel())->toBe('Main Dashboard');

    $this->withSession([AdminCenterContext::SESSION_KEY => $org['centerA']->id]);
    resetFilamentNavigationRequest();

    $centerPage = $this->actingAs($coordinator)
        ->get('/admin/attendances')
        ->assertOk()
        ->assertSee('Change Center', false)
        ->assertSee('Back to All Centers', false)
        ->assertSee($org['centerA']->name, false)
        ->assertDontSee('ft-coord-modules', false)
        ->assertDontSee('Center modules', false)
        ->assertDontSee('Switch center', false);

    expect($centerPage->getContent())->toMatch('/<li[^>]*fi-active[^>]*>[\s\S]*?href="[^"]*\/admin\/attendances"/');
    expect(session(AdminCenterContext::SESSION_KEY))->toBe($org['centerA']->id);
    expect(coordinatorSidebarLabels())->toEqualCanonicalizing(centerSidebarLabels())
        ->and(array_values(array_intersect(mainSidebarLabels(), coordinatorSidebarLabels())))->toBe(['Main Dashboard', 'Center Dashboard', 'Expenses']);

    $this->actingAs($coordinator)
        ->post(route('admin.clear-center'))
        ->assertRedirect('/admin/centers')
        ->assertSessionMissing(AdminCenterContext::SESSION_KEY);

    $this->actingAs($coordinator)->get('/admin')->assertOk();

    expect(coordinatorSidebarLabels())->toEqualCanonicalizing(mainSidebarLabels());

    $this->flushSession();
    $this->actingAs($org['admin'])->get('/admin')->assertOk();

    expect(coordinatorSidebarLabels())->toEqualCanonicalizing(adminMainSidebarLabels())
        ->and(array_values(array_intersect($removed, coordinatorSidebarLabels())))->toBe([])
        ->and(AppUpdateSettings::shouldRegisterNavigation())->toBeTrue()
        ->and(DeviceManagement::shouldRegisterNavigation())->toBeTrue()
        ->and(OrgUserResource::shouldRegisterNavigation())->toBeTrue()
        ->and(OrgUserResource::getNavigationLabel())->toBe('Team Management')
        ->and(EmployeeResource::shouldRegisterNavigation())->toBeFalse();

    $this->flushSession();
    $this->withSession([AdminCenterContext::SESSION_KEY => $org['centerA']->id]);
    resetFilamentNavigationRequest();
    $adminAttendance = $this->actingAs($org['admin'])
        ->get('/admin/attendances')
        ->assertOk()
        ->assertSee('Change Center', false)
        ->assertSee('Back to All Centers', false)
        ->assertDontSee('Back to Center Dashboard', false);

    expect($adminAttendance->getContent())->toMatch('/<li[^>]*fi-active[^>]*>[\s\S]*?href="[^"]*\/admin\/attendances"/');
    expect(coordinatorSidebarLabels())->toEqualCanonicalizing(adminCenterSidebarLabels())
        ->and(EmployeeResource::getNavigationLabel())->toBe('Users');

    $this->actingAs($org['admin'])
        ->get('/admin/center-dashboard')
        ->assertOk()
        ->assertDontSee('ft-coord-modules', false)
        ->assertDontSee('Center modules', false)
        ->assertDontSee('Switch center', false);

    $this->actingAs($org['admin'])
        ->get('/admin')
        ->assertOk()
        ->assertSee('Main Dashboard')
        ->assertSee('App Update Settings')
        ->assertSee('Device Management')
        ->assertSessionMissing(AdminCenterContext::SESSION_KEY);

    expect(coordinatorSidebarLabels())->toEqualCanonicalizing(adminMainSidebarLabels());

    $this->flushSession();
    $this->actingAs($org['director'])->get('/admin')->assertOk();

    expect(coordinatorSidebarLabels())->toEqualCanonicalizing(mainSidebarLabels())
        ->and(array_values(array_intersect($removed, coordinatorSidebarLabels())))->toBe([])
        ->and(Dashboard::getNavigationLabel())->toBe('Main Dashboard')
        ->and(CenterDashboard::canAccess())->toBeTrue()
        ->and(OrgUserResource::shouldRegisterNavigation())->toBeTrue()
        ->and(EmployeeResource::shouldRegisterNavigation())->toBeFalse()
        ->and(AppUpdateSettings::shouldRegisterNavigation())->toBeFalse()
        ->and(DeviceManagement::shouldRegisterNavigation())->toBeFalse();

    $this->actingAs($org['director'])->get('/admin/center-dashboard')->assertRedirect('/admin/centers');
    $this->actingAs($org['director'])->get('/admin/reports')->assertRedirect('/admin/centers');

    $this->withSession([AdminCenterContext::SESSION_KEY => $org['centerA']->id]);

    $this->actingAs($org['director'])
        ->get('/admin/center-dashboard')
        ->assertOk()
        ->assertSee($org['centerA']->name.' Dashboard')
        ->assertSee('Change Center', false)
        ->assertSee('Back to All Centers', false)
        ->assertDontSee('ft-coord-modules', false);

    expect(coordinatorSidebarLabels())->toEqualCanonicalizing(centerSidebarLabels());

    expect(fn () => app(AdminCenterContext::class)->select($org['director'], 999999))
        ->toThrow(ValidationException::class);

    $this->flushSession();
    $this->actingAs($org['centerManager'])->get('/admin')
        ->assertOk()
        ->assertSee('/admin/expense-claims', false);

    expect(coordinatorSidebarLabels())->toEqualCanonicalizing([
        'Main Dashboard',
        'Center Dashboard',
        'Users',
        'Expenses',
    ])
        ->and(array_values(array_intersect(array_merge(['Team Management'], $removed), coordinatorSidebarLabels())))->toBe([])
        ->and(EmployeeResource::getNavigationLabel())->toBe('Users')
        ->and(EmployeeResource::shouldRegisterNavigation())->toBeTrue()
        ->and(Dashboard::getNavigationLabel())->toBe('Main Dashboard');

    $this->actingAs($org['centerManager'])->get('/admin/attendances')->assertRedirect('/admin/centers');

    expect(fn () => app(AdminCenterContext::class)->select($org['centerManager'], $org['centerB']->id))
        ->toThrow(ValidationException::class);

    app(AdminCenterContext::class)->select($org['centerManager'], $org['centerA']->id);
    $this->withSession([AdminCenterContext::SESSION_KEY => $org['centerA']->id]);

    $this->actingAs($org['centerManager'])
        ->get('/admin/center-dashboard')
        ->assertOk()
        ->assertSee('Change Center', false)
        ->assertSee('Back to All Centers', false)
        ->assertSee($org['centerA']->name, false);

    expect(coordinatorSidebarLabels())->toEqualCanonicalizing(centerSidebarLabels())
        ->and(coordinatorSidebarLabels())->not->toContain('Users', 'Team Management');

    $this->actingAs($org['centerManager'])
        ->post(route('admin.clear-center'))
        ->assertRedirect('/admin/centers')
        ->assertSessionMissing(AdminCenterContext::SESSION_KEY);

    $this->actingAs($org['centerManager'])->get('/admin/centers')
        ->assertOk()
        ->assertSee('/admin/expense-claims', false);

    expect(coordinatorSidebarLabels())->toEqualCanonicalizing([
        'Main Dashboard',
        'Center Dashboard',
        'Users',
        'Expenses',
    ]);
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

    $this->actingAs($coordinator)->get('/admin/attendances')->assertRedirect('/admin/centers');
    $this->actingAs($coordinator)->get('/admin/employee-routes')->assertRedirect('/admin/centers');
    $this->actingAs($coordinator)->get('/admin/team-activities')->assertRedirect('/admin/centers');
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
        ->assertDontSee('ft-coord-modules', false)
        ->assertDontSee('Center modules', false)
        ->assertDontSee('Switch center', false)
        ->assertSee('Change Center', false)
        ->assertSee('Back to All Centers', false)
        ->assertDontSee('Back to Center Dashboard', false);

    expect(coordinatorSidebarLabels())->toEqualCanonicalizing(centerSidebarLabels());

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
});

it('keeps Expenses under the Expenses group on home and open-center sidebars', function () {
    $org = seedOrg();
    $coordinator = navigationCoordinator($org);

    // Center Manager Dashboard is the main Filament dashboard for that role.
    $managerHome = $this->actingAs($org['centerManager'])
        ->get('/admin')
        ->assertOk()
        ->assertSee('Center Manager Dashboard');
    assertExpensesSidebar($managerHome);

    $this->withSession([AdminCenterContext::SESSION_KEY => $org['centerA']->id]);
    resetFilamentNavigationRequest();

    $managerCenter = $this->actingAs($org['centerManager'])
        ->get('/admin/center-dashboard')
        ->assertOk();
    assertExpensesSidebar($managerCenter);

    $this->flushSession();

    $adminHome = $this->actingAs($org['admin'])
        ->get('/admin')
        ->assertOk()
        ->assertSee('Main Admin Dashboard');
    assertExpensesSidebar($adminHome);

    $this->withSession([AdminCenterContext::SESSION_KEY => $org['centerA']->id]);
    resetFilamentNavigationRequest();

    // Individual Center Dashboard opened by an admin.
    $adminCenter = $this->actingAs($org['admin'])
        ->get('/admin/center-dashboard')
        ->assertOk()
        ->assertSee($org['centerA']->name.' Dashboard');
    assertExpensesSidebar($adminCenter);

    // The centers list stays on the center sidebar while a center is selected.
    $adminCenters = $this->actingAs($org['admin'])
        ->get('/admin/centers')
        ->assertOk();
    assertExpensesSidebar($adminCenters);

    $this->flushSession();

    $coordinatorHome = $this->actingAs($coordinator)->get('/admin')->assertOk();
    assertExpensesSidebar($coordinatorHome);

    $this->withSession([AdminCenterContext::SESSION_KEY => $org['centerA']->id]);
    resetFilamentNavigationRequest();
    $coordinatorCenter = $this->actingAs($coordinator)->get('/admin/center-dashboard')->assertOk();
    assertExpensesSidebar($coordinatorCenter);

    $this->flushSession();

    $directorHome = $this->actingAs($org['director'])->get('/admin')->assertOk();
    assertExpensesSidebar($directorHome);

    $this->withSession([AdminCenterContext::SESSION_KEY => $org['centerA']->id]);
    resetFilamentNavigationRequest();
    $directorCenter = $this->actingAs($org['director'])->get('/admin/center-dashboard')->assertOk();
    assertExpensesSidebar($directorCenter);

    $this->flushSession();
    $this->actingAs($org['userA']);
    expect(ExpenseClaimResource::canAccess())->toBeFalse()
        ->and(ExpenseClaimResource::shouldRegisterNavigation())->toBeFalse()
        ->and(expenseSidebarLink())->toBeNull();
    $this->actingAs($org['userA'])->get('/admin/expense-claims')->assertForbidden();

    $this->actingAs($org['projectHead']);
    expect(ExpenseClaimResource::canAccess())->toBeFalse()
        ->and(ExpenseClaimResource::shouldRegisterNavigation())->toBeFalse()
        ->and(expenseSidebarLink())->toBeNull();
    $this->actingAs($org['projectHead'])->get('/admin/expense-claims')->assertForbidden();
});
