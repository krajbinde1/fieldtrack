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
use App\Filament\Resources\AdmissionTargets\Pages\ListAdmissionTargets;
use App\Filament\Resources\Attendances\Pages\ListAttendances;
use App\Filament\Resources\Centers\CenterResource;
use App\Filament\Resources\Centers\Pages\ListCenters;
use App\Filament\Resources\LeaveRequests\Pages\ListLeaveRequests;
use App\Filament\Resources\OrgUsers\OrgUserResource;
use App\Filament\Resources\OrgUsers\Pages\ListOrgUsers;
use App\Filament\Resources\Schemes\Pages\ListSchemes;
use App\Filament\Widgets\DirectorAdminStatsWidget;
use App\Filament\Widgets\DirectorConfirmedAdmissionsByCenterWidget;
use App\Models\Admission;
use App\Models\AdmissionTarget;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\AdminCenterContext;
use App\Services\OrganizationAccessService;
use App\Support\AttendanceCalendar;
use Filament\Actions\Testing\TestAction;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function rememberAdminCenter(int $centerId): array
{
    session()->put(AdminCenterContext::SESSION_KEY, $centerId);

    return [AdminCenterContext::SESSION_KEY => $centerId];
}

function centerContextNavLabels(): array
{
    app()->forgetInstance(\Filament\Navigation\NavigationManager::class);

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

it('opens the main admin dashboard across all authorized centers', function () {
    $org = seedOrg();

    $this->actingAs($org['admin'])->get('/admin')
        ->assertOk()
        ->assertSee('Main Admin Dashboard')
        ->assertSee('Main Dashboard')
        ->assertSee('Center Dashboard')
        ->assertSee('Centers')
        ->assertSee('Team Management')
        ->assertSee('System')
        ->assertSee('App Update Settings')
        ->assertSee('Device Management')
        ->assertDontSee('Change Center')
        ->assertDontSee('Back to All Centers');

    expect(Dashboard::getNavigationLabel())->toBe('Main Dashboard')
        ->and(CenterDashboard::getNavigationLabel())->toBe('Center Dashboard')
        ->and(centerContextNavLabels())->toEqualCanonicalizing([
            'Main Dashboard',
            'Center Dashboard',
            'Team Management',
            'App Update Settings',
            'Device Management',
        ])
        ->and(CenterResource::getNavigationLabel())->toBe('Center Dashboard')
        ->and(CenterResource::shouldRegisterNavigation())->toBeTrue()
        ->and(AppUpdateSettings::shouldRegisterNavigation())->toBeTrue()
        ->and(DeviceManagement::shouldRegisterNavigation())->toBeTrue()
        ->and(OrgUserResource::shouldRegisterNavigation())->toBeTrue()
        ->and(OrgUserResource::getNavigationLabel())->toBe('Team Management');

    $groupLabels = array_map(
        fn ($group) => $group->getLabel(),
        filament()->getNavigation(),
    );
    expect($groupLabels[array_key_last($groupLabels)])->toBe('System');

    $this->actingAs($org['admin'])->get('/admin/users')->assertOk()->assertSee('Team Management');

    foreach ([
        '/admin/attendances',
        '/admin/employee-routes',
        '/admin/admissions',
        '/admin/admission-targets',
        '/admin/leave-requests',
        '/admin/reports',
    ] as $uri) {
        $this->actingAs($org['admin'])->get($uri)->assertRedirect('/admin/centers');
    }

    $this->actingAs($org['admin'])->get('/admin/center-dashboard')->assertRedirect('/admin/centers');
    $this->actingAs($org['admin'])->get('/admin/centers')->assertOk()->assertSee('Center A')->assertSee('Center B');
    $this->actingAs($org['admin'])->get('/admin/app-update-settings')->assertOk();
    $this->actingAs($org['admin'])->get('/admin/device-management')->assertOk();

    $cards = Livewire::actingAs($org['admin'])
        ->test(DirectorAdminStatsWidget::class)
        ->instance()
        ->getCards();

    expect($cards[0]['label'])->toBe('Total Centers')
        ->and($cards[0]['value'])->toBe('2')
        ->and($cards[0]['url'])->toContain('/admin/centers');

    Livewire::actingAs($org['admin'])
        ->test(DirectorConfirmedAdmissionsByCenterWidget::class)
        ->assertSee('Center A')
        ->assertSee('Center B');
});

it('persists the selected center and scopes lists, filters, and direct urls', function () {
    $org = seedOrg();
    $today = AttendanceCalendar::today()->toDateString();

    $otherManager = User::create([
        'name' => 'Other CM',
        'email' => 'other-cm@test.local',
        'login_id' => 'othercm',
        'password' => 'CenterMgr@123',
        'role' => UserRole::CenterManager->value,
        'is_active' => true,
    ]);
    $otherManager->managedCenters()->attach($org['centerB']->id);

    $attendanceA = Attendance::create([
        'employee_id' => $org['empA']->id,
        'attendance_date' => $today,
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);
    $attendanceB = Attendance::create([
        'employee_id' => $org['empB']->id,
        'attendance_date' => $today,
        'punch_in_time' => '09:30:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);

    $admissionA = Admission::create([
        'scheme_id' => $org['projectA']->id,
        'center_id' => $org['centerA']->id,
        'employee_id' => $org['empA']->id,
        'first_name' => 'Asha',
        'last_name' => 'One',
        'full_name' => 'Asha One',
        'status' => AdmissionStatus::Submitted,
    ]);
    $admissionB = Admission::create([
        'scheme_id' => $org['projectB']->id,
        'center_id' => $org['centerB']->id,
        'employee_id' => $org['empB']->id,
        'first_name' => 'Bina',
        'last_name' => 'Two',
        'full_name' => 'Bina Two',
        'status' => AdmissionStatus::Submitted,
    ]);

    $leaveA = LeaveRequest::create([
        'employee_id' => $org['empA']->id,
        'scheme_id' => $org['projectA']->id,
        'center_id' => $org['centerA']->id,
        'leave_type' => 'casual',
        'from_date' => '2026-11-20',
        'to_date' => '2026-11-20',
        'total_days' => 1,
        'reason' => 'Center A leave',
        'status' => 'pending',
    ]);
    $leaveB = LeaveRequest::create([
        'employee_id' => $org['empB']->id,
        'scheme_id' => $org['projectB']->id,
        'center_id' => $org['centerB']->id,
        'leave_type' => 'casual',
        'from_date' => '2026-11-21',
        'to_date' => '2026-11-21',
        'total_days' => 1,
        'reason' => 'Center B leave',
        'status' => 'pending',
    ]);

    $targetA = AdmissionTarget::create([
        'employee_id' => $org['empA']->id,
        'center_id' => $org['centerA']->id,
        'scheme_id' => $org['projectA']->id,
        'target_type' => 'weekly',
        'period_start' => '2026-11-16',
        'period_end' => '2026-11-22',
        'target_count' => 4,
    ]);
    $targetB = AdmissionTarget::create([
        'employee_id' => $org['empB']->id,
        'center_id' => $org['centerB']->id,
        'scheme_id' => $org['projectB']->id,
        'target_type' => 'weekly',
        'period_start' => '2026-11-16',
        'period_end' => '2026-11-22',
        'target_count' => 6,
    ]);

    Livewire::actingAs($org['admin'])
        ->test(SelectCenter::class)
        ->fillForm(['center_id' => $org['centerA']->id])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertRedirect('/admin/center-dashboard');

    expect(session(AdminCenterContext::SESSION_KEY))->toBe($org['centerA']->id);

    $this->withSession(rememberAdminCenter($org['centerA']->id));

    $this->actingAs($org['admin'])
        ->get('/admin/center-dashboard')
        ->assertOk()
        ->assertSee('Change Center')
        ->assertSee('Back to All Centers')
        ->assertSee($org['centerA']->name.' Dashboard')
        ->assertDontSee('Total Centers')
        ->assertDontSee('Main Admin Dashboard')
        ->assertDontSee('ft-coord-modules', false)
        ->assertDontSee('Center modules', false)
        ->assertSee('System')
        ->assertSee('App Update Settings')
        ->assertSee('Device Management');

    expect(centerContextNavLabels())->toEqualCanonicalizing([
        'Main Dashboard',
        'Center Dashboard',
        'Attendances',
        'Employee Routes',
        'Admissions',
        'Admission Targets',
        'Leave Requests',
        'Reports',
        'App Update Settings',
        'Device Management',
    ]);

    $this->actingAs($org['admin'])
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('Change Center')
        ->assertSee('Main Admin Dashboard')
        ->assertSee('Main Dashboard')
        ->assertSee('Total Centers')
        ->assertDontSee($org['centerA']->name.' Dashboard')
        ->assertSessionMissing(AdminCenterContext::SESSION_KEY);

    $this->withSession(rememberAdminCenter($org['centerA']->id));

    Livewire::actingAs($org['admin'])
        ->test(ListAttendances::class)
        ->assertCanSeeTableRecords([$attendanceA])
        ->assertCanNotSeeTableRecords([$attendanceB]);

    Livewire::actingAs($org['admin'])
        ->test(ListAdmissions::class)
        ->assertCanSeeTableRecords([$admissionA])
        ->assertCanNotSeeTableRecords([$admissionB]);

    Livewire::actingAs($org['admin'])
        ->test(ListLeaveRequests::class)
        ->assertCanSeeTableRecords([$leaveA])
        ->assertCanNotSeeTableRecords([$leaveB]);

    Livewire::actingAs($org['admin'])
        ->test(ListAdmissionTargets::class)
        ->assertCanSeeTableRecords([$targetA])
        ->assertCanNotSeeTableRecords([$targetB]);

    Livewire::actingAs($org['admin'])
        ->test(ListOrgUsers::class)
        ->assertCanSeeTableRecords([$org['centerManager'], $org['projectHead']])
        ->assertCanNotSeeTableRecords([$otherManager, $org['director']]);

    Livewire::actingAs($org['admin'])
        ->test(DirectorConfirmedAdmissionsByCenterWidget::class)
        ->assertSee('Admission Details')
        ->assertSee('Asha One')
        ->assertDontSee('Bina Two')
        ->assertDontSee('Center-wise Confirmed Admissions');

    Livewire::actingAs($org['admin'])
        ->test(DirectorConfirmedAdmissionsByCenterWidget::class, ['organizationWide' => true])
        ->assertSee('Center-wise Confirmed Admissions')
        ->assertSee('Center A')
        ->assertSee('Center B')
        ->assertDontSee('Admission Details');

    $this->actingAs($org['admin'])->get('/admin/attendances/'.$attendanceA->id)->assertOk();
    $this->actingAs($org['admin'])->get('/admin/attendances/'.$attendanceB->id)->assertNotFound();
    $this->actingAs($org['admin'])->get('/admin/employee-routes/'.$attendanceB->id)->assertNotFound();
    $this->actingAs($org['admin'])->get('/admin/admissions/'.$admissionB->id)->assertNotFound();
    $this->actingAs($org['admin'])->get('/admin/leave-requests/'.$leaveB->id)->assertNotFound();
    $this->actingAs($org['admin'])->get('/admin/admission-targets/'.$targetB->id)->assertNotFound();
    $this->actingAs($org['admin'])->get('/admin/users/'.$otherManager->id.'/edit')->assertNotFound();
    $this->actingAs($org['admin'])
        ->get('/admin/attendances/employee/'.$org['empB']->id.'/present-days')
        ->assertNotFound();

    $exportIds = AdmissionResource::getEloquentQuery()->pluck('id')->all();
    expect($exportIds)->toContain($admissionA->id)->not->toContain($admissionB->id);

    app(AdminCenterContext::class)->select($org['admin'], $org['centerB']->id);
    expect(session(AdminCenterContext::SESSION_KEY))->toBe($org['centerB']->id);

    $this->withSession(rememberAdminCenter($org['centerB']->id));

    Livewire::actingAs($org['admin'])
        ->test(ListAttendances::class)
        ->assertCanSeeTableRecords([$attendanceB])
        ->assertCanNotSeeTableRecords([$attendanceA]);
});

it('rejects a center the admin is not authorized to use', function () {
    $org = seedOrg();

    app(AdminCenterContext::class)->select($org['admin'], $org['centerA']->id);

    expect(fn () => app(AdminCenterContext::class)->select($org['admin'], 999999))
        ->toThrow(ValidationException::class);

    expect(session(AdminCenterContext::SESSION_KEY))->toBe($org['centerA']->id);

    Livewire::actingAs($org['admin'])
        ->test(SelectCenter::class)
        ->fillForm(['center_id' => 999999])
        ->call('save')
        ->assertHasFormErrors(['center_id']);

    expect(session(AdminCenterContext::SESSION_KEY))->toBe($org['centerA']->id);

    $this->actingAs($org['admin'])
        ->withSession([AdminCenterContext::SESSION_KEY => 999999])
        ->get('/admin')
        ->assertOk()
        ->assertSee('Main Admin Dashboard')
        ->assertSessionMissing(AdminCenterContext::SESSION_KEY);
});

it('selects a center from the centers list and can return to every center', function () {
    $org = seedOrg();

    $cards = Livewire::actingAs($org['admin'])
        ->test(DirectorAdminStatsWidget::class)
        ->instance()
        ->getCards();
    $allEmployees = (int) $cards[1]['value'];

    Livewire::actingAs($org['admin'])
        ->test(ListCenters::class)
        ->assertSee('Center A')
        ->assertSee('Center B')
        ->callAction(TestAction::make('openDashboard')->table($org['centerA']))
        ->assertRedirect('/admin/center-dashboard');

    expect(session(AdminCenterContext::SESSION_KEY))->toBe($org['centerA']->id);

    $managerEmployee = Employee::create([
        'center_id' => $org['centerA']->id,
        'full_name' => 'Manager Profile',
        'mobile' => '9000000091',
        'designation' => 'Manager',
        'status' => true,
    ]);
    $org['centerManager']->update(['employee_id' => $managerEmployee->id]);
    Employee::create([
        'center_id' => $org['centerA']->id,
        'full_name' => 'Inactive Emp',
        'mobile' => '9000000092',
        'status' => false,
    ]);
    $org['empA']->update(['designation' => 'Field Executive']);

    $this->withSession(rememberAdminCenter($org['centerA']->id));

    $expectedEmployees = app(OrganizationAccessService::class)
        ->fieldEmployeeQuery($org['admin'])
        ->where('center_id', $org['centerA']->id)
        ->count();

    $mainCards = Livewire::actingAs($org['admin'])
        ->test(DirectorAdminStatsWidget::class, ['organizationWide' => true])
        ->instance()
        ->getCards();

    expect($mainCards[0]['label'])->toBe('Total Centers')
        ->and((int) $mainCards[1]['value'])->toBe($allEmployees);

    $scoped = Livewire::actingAs($org['admin'])
        ->test(DirectorAdminStatsWidget::class)
        ->instance()
        ->getCards();

    expect(array_column($scoped, 'label'))->not->toContain('Total Centers')
        ->and(array_column($scoped, 'label'))->toBe([
            'Total Employees',
            'Punched In Today',
            'Active Routes',
            'Attendance Today',
            'Total Admissions',
            'Today Confirmed Admissions',
            'This Month Confirmed Admissions',
            'Pending Leave Requests',
        ])
        ->and($scoped[3]['hint'])->toBe('Present / Half Day / Absent / Running / On Leave')
        ->and($scoped[3]['value'])->toBe('0 / 0 / 0 / 0 / 0')
        ->and((int) $scoped[0]['value'])->toBe($expectedEmployees)
        ->and($expectedEmployees)->toBe(1)
        ->and($scoped[0]['url'])->toContain('employees=1')
        ->and($scoped[0]['url'])->toContain('center='.$org['centerA']->id)
        ->and((int) $scoped[0]['value'])->toBeLessThan($allEmployees);

    Livewire::actingAs($org['admin'])
        ->test(DirectorConfirmedAdmissionsByCenterWidget::class)
        ->assertSee('Admission Details')
        ->assertDontSee('Center-wise Confirmed Admissions')
        ->assertDontSee('Center B');

    $this->actingAs($org['admin'])
        ->post(route('admin.clear-center'))
        ->assertRedirect('/admin/centers')
        ->assertSessionMissing(AdminCenterContext::SESSION_KEY);

    $this->actingAs($org['admin'])
        ->get('/admin/centers')
        ->assertOk()
        ->assertSee('Center A')
        ->assertSee('Center B')
        ->assertDontSee('Back to All Centers');

    Livewire::actingAs($org['admin'])
        ->test(ListCenters::class)
        ->assertCanSeeTableRecords([$org['centerA'], $org['centerB']]);

    Livewire::actingAs($org['centerManager'])
        ->test(ListCenters::class)
        ->assertActionVisible(TestAction::make('openDashboard')->table($org['centerA']));

    $this->withSession(rememberAdminCenter($org['centerA']->id));

    Livewire::actingAs($org['admin'])
        ->test(ListOrgUsers::class, [
            'employees' => 1,
            'center' => $org['centerA']->id,
        ])
        ->assertSee('Employee Code')
        ->assertSee('Designation')
        ->assertSee('Mobile')
        ->assertSee('Status')
        ->assertSee('View')
        ->assertSee($org['empA']->employee_code)
        ->assertSee('Emp A')
        ->assertSee('Field Executive')
        ->assertSee('9000000001')
        ->assertSee('Center A')
        ->assertSee('Active')
        ->assertDontSee('Manager Profile')
        ->assertDontSee('Inactive Emp')
        ->assertDontSee('Emp B');

    $this->actingAs($org['admin'])
        ->get(OrgUserResource::getUrl('viewEmployee', [
            'employee' => $org['empA'],
            'center' => $org['centerA']->id,
        ]))
        ->assertOk()
        ->assertSee($org['empA']->employee_code)
        ->assertSee('Field Executive')
        ->assertSee('9000000001')
        ->assertSee('Center A')
        ->assertSee('Active');

    $this->actingAs($org['admin'])
        ->get(OrgUserResource::getUrl('viewEmployee', [
            'employee' => $org['empB'],
            'center' => $org['centerA']->id,
        ]))
        ->assertNotFound();

    Livewire::actingAs($org['admin'])
        ->test(ListOrgUsers::class)
        ->assertCanSeeTableRecords([$org['projectHead'], $org['centerManager']])
        ->assertCanNotSeeTableRecords([$org['userA']]);
});

it('clears the selected center when an admin logs out', function () {
    $org = seedOrg();

    $this->actingAs($org['admin'])
        ->withSession([AdminCenterContext::SESSION_KEY => $org['centerA']->id])
        ->post('/admin/logout')
        ->assertRedirect('/admin/login')
        ->assertSessionMissing(AdminCenterContext::SESSION_KEY);

    $this->assertGuest();
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('does not force center selection on a center manager', function () {
    $org = seedOrg();
    $today = AttendanceCalendar::today()->toDateString();

    $attendanceA = Attendance::create([
        'employee_id' => $org['empA']->id,
        'attendance_date' => $today,
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);
    $attendanceB = Attendance::create([
        'employee_id' => $org['empB']->id,
        'attendance_date' => $today,
        'punch_in_time' => '09:30:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);

    $this->actingAs($org['centerManager'])
        ->get('/admin')
        ->assertOk()
        ->assertSee('Main Dashboard')
        ->assertDontSee('Change Center')
        ->assertSee('Center Dashboard');

    $this->actingAs($org['centerManager'])->get('/admin/center-dashboard')->assertRedirect('/admin/centers');
    expect(centerContextNavLabels())->toEqualCanonicalizing(['Main Dashboard', 'Center Dashboard', 'Users'])
        ->and(Dashboard::getNavigationLabel())->toBe('Main Dashboard');

    $this->actingAs($org['centerManager'])->get('/admin/attendances')->assertRedirect('/admin/centers');
    $this->actingAs($org['centerManager'])->get('/admin/centers')->assertOk();

    expect(SelectCenter::canAccess())->toBeTrue();

    $this->withSession(rememberAdminCenter($org['centerB']->id));

    Livewire::actingAs($org['centerManager'])
        ->test(ListAttendances::class)
        ->assertCanSeeTableRecords([$attendanceA])
        ->assertCanNotSeeTableRecords([$attendanceB]);
});

it('does not force center selection on a director', function () {
    $org = seedOrg();
    $today = AttendanceCalendar::today()->toDateString();

    $attendanceA = Attendance::create([
        'employee_id' => $org['empA']->id,
        'attendance_date' => $today,
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);
    $attendanceB = Attendance::create([
        'employee_id' => $org['empB']->id,
        'attendance_date' => $today,
        'punch_in_time' => '09:30:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);

    $this->actingAs($org['director'])
        ->get('/admin')
        ->assertOk()
        ->assertSee('Main Dashboard')
        ->assertDontSee('Change Center')
        ->assertSee('Center Dashboard')
        ->assertSee('Team Management');

    $this->actingAs($org['director'])->get('/admin/center-dashboard')->assertRedirect('/admin/centers');
    expect(CenterDashboard::canAccess())->toBeTrue()
        ->and(centerContextNavLabels())->toEqualCanonicalizing(['Main Dashboard', 'Center Dashboard', 'Team Management'])
        ->and(Dashboard::getNavigationLabel())->toBe('Main Dashboard');

    expect(OrgUserResource::shouldRegisterNavigation())->toBeTrue();

    $this->actingAs($org['director'])->get('/admin/users')->assertOk();
    $this->actingAs($org['director'])->get('/admin/reports')->assertRedirect('/admin/centers');

    $this->withSession(rememberAdminCenter($org['centerA']->id));

    $this->actingAs($org['director'])
        ->get('/admin/center-dashboard')
        ->assertOk()
        ->assertSee($org['centerA']->name.' Dashboard');

    Livewire::actingAs($org['director'])
        ->test(ListAttendances::class)
        ->assertCanSeeTableRecords([$attendanceA])
        ->assertCanNotSeeTableRecords([$attendanceB]);
});

it('does not show center switching to a project head', function () {
    $org = seedOrg();

    $this->actingAs($org['projectHead'])
        ->get('/admin')
        ->assertForbidden();

    $this->actingAs($org['projectHead'])->get('/admin/center-dashboard')->assertForbidden();
    $this->actingAs($org['projectHead'])->get('/admin/attendances')->assertForbidden();

    expect(SelectCenter::canAccess())->toBeFalse()
        ->and(CenterDashboard::shouldRegisterNavigation())->toBeFalse()
        ->and(Dashboard::getNavigationLabel())->toBe('Dashboard');
});

it('rejects a center the center manager is not assigned to', function () {
    $org = seedOrg();

    $this->actingAs($org['centerManager'])
        ->get('/admin/select-center')
        ->assertOk();

    expect(fn () => app(AdminCenterContext::class)->select($org['centerManager'], $org['centerB']->id))
        ->toThrow(ValidationException::class);

    Livewire::actingAs($org['centerManager'])
        ->test(SelectCenter::class)
        ->fillForm(['center_id' => $org['centerB']->id])
        ->call('save')
        ->assertHasFormErrors(['center_id']);
});

it('keeps center management global after an admin selects a center', function () {
    $org = seedOrg();

    $this->withSession(rememberAdminCenter($org['centerA']->id));

    Livewire::actingAs($org['admin'])
        ->test(ListCenters::class)
        ->assertCanSeeTableRecords([$org['centerA'], $org['centerB']]);

    Livewire::actingAs($org['admin'])
        ->test(ListSchemes::class)
        ->assertCanSeeTableRecords([$org['projectA'], $org['projectB']]);

    $this->actingAs($org['admin'])->get('/admin/centers')->assertOk()->assertSee('Center B');
    $this->actingAs($org['admin'])->get('/admin/centers/create')->assertOk();
    $this->actingAs($org['admin'])->get('/admin/centers/'.$org['centerB']->id.'/edit')->assertOk();
});
