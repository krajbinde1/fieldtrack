<?php

use App\Enums\AdmissionStatus;
use App\Filament\Widgets\AdmissionTargetPerformanceWidget;
use App\Filament\Widgets\DirectorAdminStatsWidget;
use App\Filament\Widgets\DirectorConfirmedAdmissionsByCenterWidget;
use App\Filament\Widgets\DirectorTodayTeamActivityWidget;
use App\Filament\Widgets\FieldTrackStatsWidget;
use App\Models\Admission;
use App\Models\Attendance;
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
        ->assertSee('Pending Project Head Leaves')
        ->assertSee('Today Confirmed Admissions')
        ->assertSee('This Month Confirmed Admissions')
        ->assertSee('Attendance Today')
        ->assertSee('Present / Half Day / Absent')
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

it('keeps scheme count and admission targets on the center manager dashboard', function () {
    $org = seedOrg();

    $this->actingAs($org['centerManager']);
    expect(DirectorAdminStatsWidget::canView())->toBeFalse()
        ->and(DirectorConfirmedAdmissionsByCenterWidget::canView())->toBeFalse()
        ->and(DirectorTodayTeamActivityWidget::canView())->toBeFalse()
        ->and(FieldTrackStatsWidget::canView())->toBeTrue()
        ->and(AdmissionTargetPerformanceWidget::canView())->toBeTrue();

    $this->actingAs($org['centerManager'])
        ->get('/admin')
        ->assertOk()
        ->assertSee(FieldTrackStatsWidget::class, false)
        ->assertSee(AdmissionTargetPerformanceWidget::class, false)
        ->assertDontSee(DirectorAdminStatsWidget::class, false)
        ->assertDontSee(DirectorConfirmedAdmissionsByCenterWidget::class, false)
        ->assertDontSee(DirectorTodayTeamActivityWidget::class, false)
        ->assertDontSee('Welcome,')
        ->assertDontSee('Attendance, punch photos, GPS')
        ->assertDontSee('ft-welcome-widget');

    Livewire::actingAs($org['centerManager'])
        ->test(FieldTrackStatsWidget::class)
        ->assertSee('Schemes')
        ->assertSee('Centers')
        ->assertSee('Employees');

    Livewire::actingAs($org['centerManager'])
        ->test(AdmissionTargetPerformanceWidget::class)
        ->assertSee('Admission Target Performance');
});
