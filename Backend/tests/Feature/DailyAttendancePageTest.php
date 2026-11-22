<?php

use App\Filament\Resources\Attendances\Pages\ListAttendances;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use App\Services\AdminCenterContext;
use App\Services\Attendance\AttendanceStatusCalculator;
use App\Services\Attendance\DailyAttendanceBoard;
use App\Support\AttendanceCalendar;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('uses one attendance page for a center manager and an admin or director', function () {
    $org = seedOrg();
    session()->put(AdminCenterContext::SESSION_KEY, $org['centerA']->id);

    $manager = Livewire::actingAs($org['centerManager'])->test(ListAttendances::class);
    $admin = Livewire::actingAs($org['admin'])->test(ListAttendances::class);
    $director = Livewire::actingAs($org['director'])->test(ListAttendances::class);

    expect($manager->instance())->toBeInstanceOf(ListAttendances::class)
        ->and($admin->instance())->toBeInstanceOf(ListAttendances::class)
        ->and($director->instance())->toBeInstanceOf(ListAttendances::class);

    foreach ([$manager, $admin, $director] as $page) {
        $page->assertSee('Daily Attendance Summary')
            ->assertSee('Total Employees')
            ->assertSee('Punch In')
            ->assertSee('Punch Out')
            ->assertSee('Currently Punched In')
            ->assertSee('Not Punched In Yet')
            ->assertSee('On Leave')
            ->assertSee('Previous Day')
            ->assertSee('Next Day')
            ->assertSee('Today')
            ->assertSee('MonthlyAttendanceSummary')
            ->assertSet('attendanceDate', AttendanceCalendar::today()->toDateString());
    }

    $manager->assertSee('Center: Center A')
        ->assertDontSee('aria-label="Scheme"', false)
        ->assertDontSee('aria-label="Center"', false);

    $admin->assertSee('aria-label="Scheme"', false)
        ->assertSee('aria-label="Center"', false);

    $director->assertSee('aria-label="Scheme"', false)
        ->assertSee('aria-label="Center"', false)
        ->assertSee('All');
});

it('updates summary counts from real attendance rows when the date changes', function () {
    $org = seedOrg();
    $today = AttendanceCalendar::today()->toDateString();
    $yesterday = AttendanceCalendar::today()->copy()->subDay()->toDateString();

    Attendance::create([
        'employee_id' => $org['empA']->id,
        'attendance_date' => $today,
        'punch_in_time' => '09:00:00',
        'punch_out_time' => '17:00:00',
        'attendance_status' => AttendanceStatusCalculator::STATUS_PRESENT,
        'approval_status' => 'Pending',
    ]);
    Attendance::create([
        'employee_id' => $org['empB']->id,
        'attendance_date' => $yesterday,
        'punch_in_time' => '09:30:00',
        'attendance_status' => AttendanceStatusCalculator::STATUS_PUNCHED_IN,
        'approval_status' => 'Pending',
    ]);

    $page = Livewire::actingAs($org['director'])->test(ListAttendances::class);

    expect($page->instance()->summaryCounts())
        ->toMatchArray([
            DailyAttendanceBoard::CARD_TOTAL => 2,
            DailyAttendanceBoard::CARD_PUNCHED_IN => 1,
            DailyAttendanceBoard::CARD_PUNCHED_OUT => 1,
            DailyAttendanceBoard::CARD_CURRENTLY => 0,
            DailyAttendanceBoard::CARD_NOT_PUNCHED => 1,
            DailyAttendanceBoard::CARD_ON_LEAVE => 0,
        ]);

    $page->assertSee('8h 00m')->assertSee('View Details');

    $page->call('previousDay');

    expect($page->instance()->summaryCounts())
        ->toMatchArray([
            DailyAttendanceBoard::CARD_TOTAL => 2,
            DailyAttendanceBoard::CARD_PUNCHED_IN => 1,
            DailyAttendanceBoard::CARD_PUNCHED_OUT => 0,
            DailyAttendanceBoard::CARD_CURRENTLY => 0,
            DailyAttendanceBoard::CARD_NOT_PUNCHED => 1,
            DailyAttendanceBoard::CARD_ON_LEAVE => 0,
        ])
        ->and($page->instance()->attendanceDate)->toBe($yesterday);

    $page->call('goToToday');

    expect($page->instance()->attendanceDate)->toBe($today)
        ->and($page->instance()->summaryCounts()[DailyAttendanceBoard::CARD_PUNCHED_OUT])->toBe(1);
});

it('hides another center employee from a center manager and a coordinator', function () {
    $org = seedOrg();
    $today = AttendanceCalendar::today()->toDateString();

    $attendanceA = Attendance::create([
        'employee_id' => $org['empA']->id,
        'attendance_date' => $today,
        'punch_in_time' => '09:00:00',
        'attendance_status' => AttendanceStatusCalculator::STATUS_PUNCHED_IN,
        'approval_status' => 'Pending',
    ]);
    $attendanceB = Attendance::create([
        'employee_id' => $org['empB']->id,
        'attendance_date' => $today,
        'punch_in_time' => '10:00:00',
        'attendance_status' => AttendanceStatusCalculator::STATUS_PUNCHED_IN,
        'approval_status' => 'Pending',
    ]);

    $coordinator = User::create([
        'name' => 'Coordinator',
        'email' => 'coord-attendance@test.local',
        'login_id' => 'coordatt',
        'password' => Hash::make('Coord@123'),
        'role' => \App\Enums\UserRole::CenterCoordinator->value,
        'is_active' => true,
    ]);
    $coordinator->coordinatedCenters()->attach($org['centerA']->id);

    foreach ([$org['centerManager'], $coordinator] as $user) {
        Livewire::actingAs($user)
            ->test(ListAttendances::class)
            ->assertCanSeeTableRecords([$attendanceA])
            ->assertCanNotSeeTableRecords([$attendanceB])
            ->assertDontSee('Emp B')
            ->set('centerId', (string) $org['centerB']->id)
            ->assertCanNotSeeTableRecords([$attendanceB])
            ->assertDontSee('Emp B');
    }

    expect(Livewire::actingAs($coordinator)->test(ListAttendances::class)->instance()->showsCenterFilter())->toBeFalse();

    $coordinator->coordinatedCenters()->attach($org['centerB']->id);

    $both = Livewire::actingAs($coordinator->fresh())->test(ListAttendances::class);
    expect($both->instance()->showsCenterFilter())->toBeTrue()
        ->and($both->instance()->showsSchemeFilter())->toBeFalse();

    $both->set('centerId', (string) $org['centerB']->id)
        ->assertCanSeeTableRecords([$attendanceB])
        ->assertCanNotSeeTableRecords([$attendanceA]);
});

it('narrows the daily list when a summary card is clicked', function () {
    $org = seedOrg();
    $today = AttendanceCalendar::today()->toDateString();

    $idle = Employee::create([
        'center_id' => $org['centerA']->id,
        'full_name' => 'Idle Employee',
        'mobile' => '9000000099',
        'status' => true,
    ]);
    User::create([
        'employee_id' => $idle->id,
        'name' => 'Idle Employee',
        'email' => 'idle@test.local',
        'login_id' => '9000000099',
        'password' => Hash::make('Employee@123'),
        'role' => \App\Enums\UserRole::Employee->value,
        'is_active' => true,
    ]);

    $punched = Attendance::create([
        'employee_id' => $org['empA']->id,
        'attendance_date' => $today,
        'punch_in_time' => '09:15:00',
        'attendance_status' => AttendanceStatusCalculator::STATUS_PUNCHED_IN,
        'approval_status' => 'Pending',
    ]);
    Attendance::create([
        'employee_id' => $org['empB']->id,
        'attendance_date' => $today,
        'attendance_status' => AttendanceStatusCalculator::STATUS_LEAVE,
        'approval_status' => 'Approved',
        'remarks' => 'On Leave',
    ]);

    $page = Livewire::actingAs($org['director'])->test(ListAttendances::class);

    expect($page->instance()->summaryCounts())
        ->toMatchArray([
            DailyAttendanceBoard::CARD_TOTAL => 3,
            DailyAttendanceBoard::CARD_PUNCHED_IN => 1,
            DailyAttendanceBoard::CARD_CURRENTLY => 1,
            DailyAttendanceBoard::CARD_NOT_PUNCHED => 1,
            DailyAttendanceBoard::CARD_ON_LEAVE => 1,
        ]);

    $page->assertCanSeeTableRecords([$punched])
        ->assertSee('table.records.-'.$idle->id, false);

    $page->call('toggleCard', DailyAttendanceBoard::CARD_PUNCHED_IN)
        ->assertCanSeeTableRecords([$punched])
        ->assertDontSee('table.records.-'.$idle->id, false)
        ->assertSet('attendanceCard', DailyAttendanceBoard::CARD_PUNCHED_IN);

    $page->call('toggleCard', DailyAttendanceBoard::CARD_PUNCHED_IN)
        ->assertSet('attendanceCard', null)
        ->assertSee('table.records.-'.$idle->id, false);

    $page->call('toggleCard', DailyAttendanceBoard::CARD_ON_LEAVE)
        ->assertDontSee('table.records.-'.$idle->id, false)
        ->assertCanNotSeeTableRecords([$punched]);

    $page->call('toggleCard', DailyAttendanceBoard::CARD_NOT_PUNCHED)
        ->assertSee('table.records.-'.$idle->id, false)
        ->assertCanNotSeeTableRecords([$punched]);
});
