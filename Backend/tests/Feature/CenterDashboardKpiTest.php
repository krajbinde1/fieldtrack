<?php

use App\Enums\LeaveStatus;
use App\Enums\UserRole;
use App\Filament\Pages\CenterDashboard;
use App\Filament\Widgets\AdmissionTargetPerformanceWidget;
use App\Filament\Widgets\DirectorAdminStatsWidget;
use App\Models\AdmissionTarget;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\AdminCenterContext;
use App\Support\AdmissionTargetPeriod;
use App\Support\AttendanceCalendar;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

function selectDashboardCenter(int $centerId): void
{
    session()->put(AdminCenterContext::SESSION_KEY, $centerId);
    test()->withSession([AdminCenterContext::SESSION_KEY => $centerId]);
}

it('scopes a selected center dashboard and keeps total centers on the main dashboard', function () {
    $org = seedOrg();
    $today = AttendanceCalendar::today()->toDateString();

    Attendance::create([
        'employee_id' => $org['empB']->id,
        'attendance_date' => $today,
        'punch_in_time' => '09:30:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
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
    LeaveRequest::create([
        'employee_id' => $org['empB']->id,
        'scheme_id' => $org['projectB']->id,
        'center_id' => $org['centerB']->id,
        'leave_type' => 'casual',
        'from_date' => $today,
        'to_date' => $today,
        'total_days' => 1,
        'reason' => 'Center B leave',
        'status' => LeaveStatus::Pending->value,
    ]);

    selectDashboardCenter($org['centerA']->id);

    $this->actingAs($org['admin'])
        ->get('/admin/center-dashboard')
        ->assertOk()
        ->assertSee($org['centerA']->name.' Dashboard')
        ->assertSee('Pending Leave Requests')
        ->assertDontSee('Total Centers')
        ->assertDontSee('Pending Project Manager Leaves')
        ->assertDontSee('Pending Leaves');

    $cards = Livewire::actingAs($org['admin'])
        ->test(DirectorAdminStatsWidget::class)
        ->instance()
        ->getCards();
    $labels = array_column($cards, 'label');
    $leave = $cards[array_search('Pending Leave Requests', $labels, true)];
    $punched = $cards[array_search('Punched In Today', $labels, true)];

    parse_str((string) parse_url($leave['url'], PHP_URL_QUERY), $leaveQuery);

    expect($labels)->not->toContain('Total Centers')
        ->and($labels)->not->toContain('Pending Project Manager Leaves')
        ->and($leave['value'])->toBe('1')
        ->and($punched['value'])->toBe('0 / 1')
        ->and($leave['url'])->toContain('center='.$org['centerA']->id)
        ->and($punched['url'])->toContain('center='.$org['centerA']->id)
        ->and((int) data_get($leaveQuery, 'filters.center_id.value'))->toBe($org['centerA']->id);

    $this->actingAs($org['admin'])
        ->get('/admin')
        ->assertOk()
        ->assertSee('Main Admin Dashboard')
        ->assertSee('Total Centers')
        ->assertDontSee($org['centerA']->name.' Dashboard');

    $mainCards = Livewire::actingAs($org['admin'])
        ->test(DirectorAdminStatsWidget::class, ['organizationWide' => true])
        ->instance()
        ->getCards();

    expect($mainCards[0]['label'])->toBe('Total Centers')
        ->and((int) $mainCards[0]['value'])->toBe(2);

    $coordinator = User::query()->create([
        'name' => 'Coordinator',
        'email' => 'coord-dash@test.local',
        'login_id' => 'coorddash',
        'password' => Hash::make('Coordinator@123'),
        'role' => UserRole::CenterCoordinator->value,
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $coordinator->coordinatedCenters()->attach([$org['centerA']->id, $org['centerB']->id]);

    [$weekStart, $weekEnd] = AdmissionTargetPeriod::resolve('this_week');
    AdmissionTarget::create([
        'employee_id' => $org['empA']->id,
        'center_id' => $org['centerA']->id,
        'scheme_id' => $org['projectA']->id,
        'target_type' => 'weekly',
        'period_start' => $weekStart->toDateString(),
        'period_end' => $weekEnd->toDateString(),
        'target_count' => 7,
    ]);
    AdmissionTarget::create([
        'employee_id' => $org['empB']->id,
        'center_id' => $org['centerB']->id,
        'scheme_id' => $org['projectB']->id,
        'target_type' => 'weekly',
        'period_start' => $weekStart->toDateString(),
        'period_end' => $weekEnd->toDateString(),
        'target_count' => 40,
    ]);

    $this->flushSession();
    selectDashboardCenter($org['centerA']->id);

    $this->actingAs($coordinator)
        ->get('/admin/center-dashboard')
        ->assertOk()
        ->assertSee($org['centerA']->name.' Dashboard')
        ->assertSee('Pending Leave Requests')
        ->assertDontSee('Total Centers')
        ->assertDontSee('Pending Project Manager Leaves');

    $coordinatorCards = Livewire::actingAs($coordinator)
        ->test(DirectorAdminStatsWidget::class)
        ->instance()
        ->getCards();
    $coordinatorLeave = collect($coordinatorCards)->firstWhere('label', 'Pending Leave Requests');

    expect(array_column($coordinatorCards, 'label'))->not->toContain('Total Centers')
        ->and($coordinatorLeave['value'])->toBe('1')
        ->and($coordinatorLeave['url'])->toContain('center='.$org['centerA']->id)
        ->and(collect($coordinatorCards)->firstWhere('label', 'Total Employees')['url'])->toContain('/admin/center-users')
        ->and(collect($coordinatorCards)->firstWhere('label', 'Total Employees')['url'])->toContain('center='.$org['centerA']->id);

    Livewire::actingAs($coordinator)
        ->test(AdmissionTargetPerformanceWidget::class)
        ->assertSee('Admission Target Performance')
        ->assertSee('7')
        ->assertDontSee('40')
        ->assertDontSee('47');

    $this->actingAs($coordinator)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Total Centers')
        ->assertDontSee($org['centerA']->name.' Dashboard');

    $this->flushSession();

    $this->actingAs($org['director'])
        ->get('/admin')
        ->assertOk()
        ->assertSee('Total Centers')
        ->assertSee('Pending Leave Requests');

    $this->actingAs($org['director'])
        ->get('/admin/center-dashboard')
        ->assertRedirect('/admin/centers');

    expect(CenterDashboard::canAccess())->toBeTrue();

    selectDashboardCenter($org['centerA']->id);

    $this->actingAs($org['director'])
        ->get('/admin/center-dashboard')
        ->assertOk()
        ->assertSee($org['centerA']->name.' Dashboard')
        ->assertSee('Pending Leave Requests')
        ->assertDontSee('Total Centers');
});
