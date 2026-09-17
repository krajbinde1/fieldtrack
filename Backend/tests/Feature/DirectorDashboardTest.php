<?php

use App\Enums\AdmissionStatus;
use App\Enums\CenterStaffRole;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Admission;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Support\AttendanceCalendar;
use Laravel\Sanctum\Sanctum;

it('returns director org dashboard counts without changing center-manager dashboard counts', function () {
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
    Admission::create([
        'scheme_id' => $org['projectA']->id,
        'center_id' => $org['centerA']->id,
        'employee_id' => $org['empA']->id,
        'first_name' => 'Submitted',
        'last_name' => 'Skip',
        'full_name' => 'Submitted Skip',
        'status' => AdmissionStatus::Submitted,
        'submitted_at' => now(),
    ]);

    Sanctum::actingAs($org['director']);
    $dashboard = $this->getJson('/api/dashboard')->assertOk()->json('data');

    expect($dashboard['active_centers'])->toBe(2)
        ->and($dashboard['employees'])->toBe(4)
        ->and($dashboard['punched_in_today'])->toBe(1)
        ->and($dashboard['active_routes'])->toBe(1)
        ->and($dashboard['confirmed_admissions'])->toBe(1)
        ->and($dashboard['pending_project_head_leaves'])->toBe(0);

    Sanctum::actingAs($org['centerManager']);
    $cm = $this->getJson('/api/dashboard')->assertOk()->json('data');
    expect($cm['employees'])->toBe(1)
        ->and($cm['punched_in_today'])->toBe(1)
        ->and($cm['pending_leaves'])->toBe(0);
});

it('lists director workforce in role priority and includes today attendance status', function () {
    $org = seedOrg();
    Employee::query()->whereKey($org['empA']->id)->update(['staff_role' => CenterStaffRole::Trainer->value]);
    Employee::query()->whereKey($org['empB']->id)->update(['staff_role' => CenterStaffRole::Mobilizer->value]);

    Attendance::create([
        'employee_id' => $org['empA']->id,
        'attendance_date' => AttendanceCalendar::today()->toDateString(),
        'punch_in_time' => '09:00:00',
        'punch_out_time' => '18:00:00',
        'attendance_status' => 'Punched Out',
        'approval_status' => 'Pending',
    ]);

    Sanctum::actingAs($org['director']);
    $rows = collect($this->getJson('/api/director/employees')->assertOk()->json('data'));
    $roles = $rows->pluck('role')->all();

    expect($roles[0])->toBe('project_head')
        ->and($roles[1])->toBe('center_manager')
        ->and($roles)->toContain('trainer')
        ->and($roles)->toContain('mobilizer');

    $trainer = $rows->firstWhere('employee_id', $org['empA']->id);
    expect($trainer['attendance_status'])->toBe('punched_out')
        ->and($trainer['scheme_name'])->toBe('Project A')
        ->and($trainer['center_name'])->toBe('Center A')
        ->and($trainer['login_id'])->toBe('9000000001');
});

it('lets a director list and act on project head leave only', function () {
    $org = seedOrg();

    $employeeLeave = LeaveRequest::create([
        'employee_id' => $org['empA']->id,
        'scheme_id' => $org['projectA']->id,
        'center_id' => $org['centerA']->id,
        'created_by_user_id' => $org['userA']->id,
        'leave_type' => LeaveType::Casual->value,
        'from_date' => '2026-09-20',
        'to_date' => '2026-09-21',
        'total_days' => 2,
        'reason' => 'Employee leave',
        'status' => LeaveStatus::Pending->value,
    ]);

    $phEmployee = Employee::create([
        'center_id' => $org['centerA']->id,
        'full_name' => 'PH Staff',
        'mobile' => '9000000099',
        'status' => true,
    ]);
    $org['projectHead']->update(['employee_id' => $phEmployee->id]);

    $phLeave = LeaveRequest::create([
        'employee_id' => $phEmployee->id,
        'scheme_id' => $org['projectA']->id,
        'center_id' => $org['centerA']->id,
        'created_by_user_id' => $org['projectHead']->id,
        'leave_type' => LeaveType::Casual->value,
        'from_date' => '2026-09-24',
        'to_date' => '2026-09-24',
        'total_days' => 1,
        'reason' => 'Project head leave',
        'status' => LeaveStatus::Pending->value,
    ]);

    Sanctum::actingAs($org['director']);
    $listed = collect($this->getJson('/api/director/leaves?status=pending')->assertOk()->json('data'))
        ->pluck('id')
        ->all();

    expect($listed)->toContain($phLeave->id)
        ->and($listed)->not->toContain($employeeLeave->id);

    $this->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonPath('data.pending_project_head_leaves', 1)
        ->assertJsonPath('data.pending_leaves', 1);

    $this->postJson('/api/director/leaves/'.$employeeLeave->id.'/approve')->assertForbidden();
    $this->postJson('/api/director/leaves/'.$phLeave->id.'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $rejectable = LeaveRequest::create([
        'employee_id' => $phEmployee->id,
        'scheme_id' => $org['projectA']->id,
        'center_id' => $org['centerA']->id,
        'created_by_user_id' => $org['projectHead']->id,
        'leave_type' => LeaveType::Sick->value,
        'from_date' => '2026-09-28',
        'to_date' => '2026-09-28',
        'total_days' => 1,
        'reason' => 'Second PH leave',
        'status' => LeaveStatus::Pending->value,
    ]);

    $this->postJson('/api/director/leaves/'.$rejectable->id.'/reject', [])
        ->assertStatus(422);
    $this->postJson('/api/director/leaves/'.$rejectable->id.'/reject', [
        'rejection_remark' => 'Coverage needed',
    ])->assertOk()->assertJsonPath('data.status', 'rejected');

    Sanctum::actingAs($org['centerManager']);
    $this->getJson('/api/manager/leaves')->assertOk();
    $managerIds = collect($this->getJson('/api/manager/leaves')->json('data'))->pluck('id')->all();
    expect($managerIds)->toContain($employeeLeave->id);
});

it('summarizes confirmed admissions by center for director monitoring', function () {
    $org = seedOrg();

    Admission::create([
        'scheme_id' => $org['projectA']->id,
        'center_id' => $org['centerA']->id,
        'employee_id' => $org['empA']->id,
        'first_name' => 'Asha',
        'last_name' => 'Patil',
        'full_name' => 'Asha Patil',
        'status' => AdmissionStatus::Confirmed,
        'confirmed_at' => now()->subDay(),
        'reviewed_by_user_id' => $org['centerManager']->id,
    ]);
    Admission::create([
        'scheme_id' => $org['projectA']->id,
        'center_id' => $org['centerA']->id,
        'employee_id' => $org['empA']->id,
        'first_name' => 'Draft',
        'last_name' => 'Skip',
        'full_name' => 'Draft Skip',
        'status' => AdmissionStatus::Draft,
    ]);
    Admission::create([
        'scheme_id' => $org['projectB']->id,
        'center_id' => $org['centerB']->id,
        'employee_id' => $org['empB']->id,
        'first_name' => 'Bina',
        'last_name' => 'More',
        'full_name' => 'Bina More',
        'status' => AdmissionStatus::Confirmed,
        'confirmed_at' => now(),
        'reviewed_by_user_id' => $org['centerManager']->id,
    ]);
    Admission::create([
        'scheme_id' => $org['projectA']->id,
        'center_id' => $org['centerA']->id,
        'employee_id' => $org['empA']->id,
        'first_name' => 'Chetan',
        'last_name' => 'Jadhav',
        'full_name' => 'Chetan Jadhav',
        'status' => AdmissionStatus::Confirmed,
        'confirmed_at' => now(),
        'reviewed_by_user_id' => $org['centerManager']->id,
    ]);

    Sanctum::actingAs($org['director']);
    $summary = $this->getJson('/api/director/admissions/confirmed-by-center')->assertOk()->json('data');
    expect($summary[0]['center_id'])->toBe($org['centerA']->id)
        ->and($summary[0]['total_confirmed'])->toBe(2)
        ->and($summary[0]['scheme_name'])->toBe('Project A')
        ->and($summary[1]['center_id'])->toBe($org['centerB']->id)
        ->and($summary[1]['total_confirmed'])->toBe(1);

    $list = $this->getJson('/api/director/admissions?status=confirmed&center_id='.$org['centerA']->id.'&sort=confirmed_at')
        ->assertOk()
        ->json('data');
    expect(collect($list)->pluck('first_name')->all())->toContain('Asha', 'Chetan')
        ->and(collect($list)->pluck('first_name')->all())->not->toContain('Draft', 'Bina');

    $detail = $this->getJson('/api/director/admissions/'.$list[0]['id'])->assertOk()->json('data');
    expect($detail['confirmed_by_name'])->toBe('CM')
        ->and($detail['can_confirm'])->toBeFalse();

    Sanctum::actingAs($org['centerManager']);
    $this->getJson('/api/manager/admissions/confirmed-by-center')->assertNotFound();
});

it('filters director active routes to currently punched-in employees', function () {
    $org = seedOrg();
    $today = AttendanceCalendar::today()->toDateString();

    Attendance::create([
        'employee_id' => $org['empA']->id,
        'attendance_date' => $today,
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);
    Attendance::create([
        'employee_id' => $org['empB']->id,
        'attendance_date' => $today,
        'punch_in_time' => '09:15:00',
        'punch_out_time' => '17:00:00',
        'attendance_status' => 'Punched Out',
        'approval_status' => 'Pending',
    ]);

    Sanctum::actingAs($org['director']);
    $active = collect($this->getJson('/api/director/route-tracking?active_only=1')->assertOk()->json('data'));
    expect($active)->toHaveCount(1)
        ->and($active->first()['employee_id'])->toBe($org['empA']->id)
        ->and($active->first()['center_name'])->toBe('Center A');
});
