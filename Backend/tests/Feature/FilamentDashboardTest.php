<?php

use App\Enums\AdmissionStatus;
use App\Enums\LeaveStatus;
use App\Filament\Widgets\AdmissionTargetPerformanceWidget;
use App\Filament\Widgets\CenterManagerDashboardWidget;
use App\Filament\Widgets\DirectorAdminStatsWidget;
use App\Filament\Widgets\DirectorConfirmedAdmissionsByCenterWidget;
use App\Filament\Widgets\DirectorTodayTeamActivityWidget;
use App\Filament\Widgets\FieldTrackStatsWidget;
use App\Models\Admission;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Support\AttendanceCalendar;
use Livewire\Livewire;

it('shows director admin management summary without scheme count or admission targets', function () {
    $org = seedOrg();
    $today = AttendanceCalendar::today()->toDateString();

    Attendance::create([
        'employee_id' => $org['empA']->id,
        'attendance_date' => $today,
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);
    Admission::create([
        'scheme_id' => $org['projectA']->id,
        'center_id' => $org['centerA']->id,
        'employee_id' => $org['empA']->id,
        'first_name' => 'Confirmed',
        'last_name' => 'One',
        'full_name' => 'Confirmed One',
        'status' => AdmissionStatus::Confirmed,
        'confirmed_at' => now(),
        'reviewed_by_user_id' => $org['centerManager']->id,
    ]);

    $this->actingAs($org['director']);
    expect(DirectorAdminStatsWidget::canView())->toBeTrue()
        ->and(DirectorConfirmedAdmissionsByCenterWidget::canView())->toBeTrue()
        ->and(DirectorTodayTeamActivityWidget::canView())->toBeTrue()
        ->and(FieldTrackStatsWidget::canView())->toBeFalse()
        ->and(AdmissionTargetPerformanceWidget::canView())->toBeFalse();

    $this->actingAs($org['director'])
        ->get('/admin')
        ->assertOk()
        ->assertSee(DirectorAdminStatsWidget::class, false)
        ->assertSee(DirectorConfirmedAdmissionsByCenterWidget::class, false)
        ->assertSee(DirectorTodayTeamActivityWidget::class, false)
        ->assertDontSee(FieldTrackStatsWidget::class, false)
        ->assertDontSee(AdmissionTargetPerformanceWidget::class, false)
        ->assertDontSee('Welcome,')
        ->assertDontSee('Attendance, punch photos, GPS')
        ->assertDontSee('ft-welcome-widget')
        ->assertSee('padding-block-start: 14px !important', false);

    Livewire::actingAs($org['director'])
        ->test(DirectorAdminStatsWidget::class)
        ->assertSee('Total Centers')
        ->assertSee('Total Employees')
        ->assertSee('Punched In Today')
        ->assertSee('Active Routes')
        ->assertSee('Pending Project Manager Leaves')
        ->assertSee('Today Confirmed Admissions')
        ->assertSee('This Month Confirmed Admissions')
        ->assertSee('Attendance Today')
        ->assertSee('Present / Half Day / Absent / Running / On Leave')
        ->assertDontSee('Schemes')
        ->assertSee('/admin/centers')
        ->assertSee('/admin/users')
        ->assertSee('/admin/attendances')
        ->assertSee('/admin/employee-routes')
        ->assertSee('/admin/leave-requests')
        ->assertSee('/admin/admissions');

    $statsHtml = Livewire::actingAs($org['director'])
        ->test(DirectorAdminStatsWidget::class)
        ->html();
    expect(substr_count($statsHtml, 'ft-dash-card '))->toBe(8)
        ->and($statsHtml)->toContain('ft-dash-stats')
        ->and($statsHtml)->toContain('fi-grid-col');

    Livewire::actingAs($org['director'])
        ->test(DirectorConfirmedAdmissionsByCenterWidget::class)
        ->assertSee('Center-wise Confirmed Admissions')
        ->assertSee('Center A');

    Livewire::actingAs($org['director'])
        ->test(DirectorTodayTeamActivityWidget::class)
        ->assertSee('Today Team Activity')
        ->assertSee('Emp A')
        ->assertSee('Working Hours')
        ->assertSee('Status');
});

it('shows the center manager dashboard for assigned centers only', function () {
    $org = seedOrg();
    $today = AttendanceCalendar::today()->toDateString();

    Attendance::create([
        'employee_id' => $org['empA']->id,
        'attendance_date' => $today,
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);
    Admission::create([
        'scheme_id' => $org['projectA']->id,
        'center_id' => $org['centerA']->id,
        'employee_id' => $org['empA']->id,
        'first_name' => 'Waiting',
        'last_name' => 'One',
        'full_name' => 'Waiting One',
        'status' => AdmissionStatus::Submitted,
        'submitted_at' => now(),
    ]);
    Admission::create([
        'scheme_id' => $org['projectB']->id,
        'center_id' => $org['centerB']->id,
        'employee_id' => $org['empB']->id,
        'first_name' => 'Other',
        'last_name' => 'Center',
        'full_name' => 'Other Center',
        'status' => AdmissionStatus::Submitted,
        'submitted_at' => now(),
    ]);
    LeaveRequest::create([
        'employee_id' => $org['empA']->id,
        'scheme_id' => $org['projectA']->id,
        'center_id' => $org['centerA']->id,
        'leave_type' => 'casual',
        'from_date' => $today,
        'to_date' => $today,
        'total_days' => 1,
        'reason' => 'Center A leave',
        'status' => LeaveStatus::Pending->value,
    ]);

    $this->actingAs($org['centerManager']);
    expect(CenterManagerDashboardWidget::canView())->toBeTrue()
        ->and(DirectorAdminStatsWidget::canView())->toBeFalse()
        ->and(FieldTrackStatsWidget::canView())->toBeTrue()
        ->and(AdmissionTargetPerformanceWidget::canView())->toBeTrue();

    $this->actingAs($org['director']);
    expect(CenterManagerDashboardWidget::canView())->toBeFalse();

    $page = $this->actingAs($org['centerManager'])
        ->get('/admin')
        ->assertOk()
        ->assertSee('Center Manager Dashboard')
        ->assertSee(CenterManagerDashboardWidget::class, false)
        ->assertDontSee(DirectorAdminStatsWidget::class, false)
        ->assertDontSee(DirectorConfirmedAdmissionsByCenterWidget::class, false)
        ->assertDontSee(DirectorTodayTeamActivityWidget::class, false)
        ->assertDontSee('Admission Target Performance')
        ->assertDontSee('Welcome,');

    Livewire::actingAs($org['centerManager'])
        ->test(CenterManagerDashboardWidget::class)
        ->assertSee('CM')
        ->assertSee('Center A')
        ->assertDontSee('Center B')
        ->assertSee('Total Employees')
        ->assertSee('Present Today')
        ->assertSee('Absent Today')
        ->assertSee('On Leave Today')
        ->assertSee('Monthly Admission Target')
        ->assertSee('Confirmed Admissions')
        ->assertSee('Pending Admissions')
        ->assertSee('Target Achievement %')
        ->assertSee('Waiting One')
        ->assertDontSee('Other Center')
        ->assertSee('Emp A')
        ->assertDontSee('Emp B')
        ->assertSee('Employee Route Tracking')
        ->assertSee('/admin/center-users')
        ->assertSee('/admin/attendances')
        ->assertSee('/admin/admissions')
        ->assertSee('/admin/leave-requests')
        ->assertSee('/admin/admission-targets')
        ->assertSee('/admin/employee-routes')
        ->call('setPreset', 'this_month')
        ->assertSet('preset', 'this_month');

    expect($page->getContent())->not->toContain('Coordinator Dashboard');
});
