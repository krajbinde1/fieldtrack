<?php

use App\Enums\AdmissionStatus;
use App\Enums\UserRole;
use App\Filament\Pages\SelectCenter;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Resources\Admissions\Pages\ListAdmissions;
use App\Filament\Resources\AdmissionTargets\Pages\ListAdmissionTargets;
use App\Filament\Resources\Attendances\Pages\ListAttendances;
use App\Filament\Resources\Centers\Pages\ListCenters;
use App\Filament\Resources\LeaveRequests\Pages\ListLeaveRequests;
use App\Filament\Resources\OrgUsers\Pages\ListOrgUsers;
use App\Filament\Resources\Schemes\Pages\ListSchemes;
use App\Filament\Widgets\DirectorConfirmedAdmissionsByCenterWidget;
use App\Models\Admission;
use App\Models\AdmissionTarget;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\AdminCenterContext;
use App\Support\AttendanceCalendar;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function rememberAdminCenter(int $centerId): array
{
    session()->put(AdminCenterContext::SESSION_KEY, $centerId);

    return [AdminCenterContext::SESSION_KEY => $centerId];
}

it('blocks an admin from the dashboard and center modules until a center is selected', function () {
    $org = seedOrg();

    $this->actingAs($org['admin'])->get('/admin/select-center')
        ->assertOk()
        ->assertSee('Select Center')
        ->assertSee('Center A')
        ->assertSee('Center B');

    foreach ([
        '/admin',
        '/admin/users',
        '/admin/attendances',
        '/admin/employee-routes',
        '/admin/admissions',
        '/admin/admission-targets',
        '/admin/leave-requests',
    ] as $uri) {
        $this->actingAs($org['admin'])->get($uri)->assertRedirect('/admin/select-center');
    }

    $this->actingAs($org['admin'])->get('/admin/centers')->assertOk();
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
        ->assertRedirect('/admin');

    expect(session(AdminCenterContext::SESSION_KEY))->toBe($org['centerA']->id);

    $this->withSession(rememberAdminCenter($org['centerA']->id));

    $this->actingAs($org['admin'])
        ->get('/admin')
        ->assertOk()
        ->assertSee('Change Center')
        ->assertSee($org['centerA']->name);

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
        ->assertSee('Center A')
        ->assertDontSee('Center B');

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
        ->assertRedirect('/admin/select-center')
        ->assertSessionMissing(AdminCenterContext::SESSION_KEY);
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
        ->assertDontSee('Change Center');

    $this->actingAs($org['centerManager'])->get('/admin/attendances')->assertOk();
    $this->actingAs($org['centerManager'])->get('/admin/centers')->assertOk();

    expect(SelectCenter::canAccess())->toBeFalse();

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
        ->assertDontSee('Change Center');

    $this->withSession(rememberAdminCenter($org['centerA']->id));

    Livewire::actingAs($org['director'])
        ->test(ListAttendances::class)
        ->assertCanSeeTableRecords([$attendanceA, $attendanceB]);
});

it('rejects center selection for a center manager', function () {
    $org = seedOrg();

    $this->actingAs($org['centerManager'])
        ->get('/admin/select-center')
        ->assertForbidden();
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
