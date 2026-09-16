<?php

use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use App\Services\Attendance\AttendanceStatusCalculator;
use App\Support\AttendanceCalendar;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function makeAttendanceEmployee(array $overrides = []): Employee
{
    static $n = 0;
    $n++;

    $scheme = \App\Models\Scheme::query()->firstOrCreate(
        ['code' => 'T'.$n],
        ['name' => 'Test Scheme '.$n, 'is_active' => true],
    );
    $center = \App\Models\Center::query()->firstOrCreate(
        ['scheme_id' => $scheme->id, 'code' => 'C'.$n],
        ['name' => 'Test Center '.$n, 'is_active' => true],
    );

    return Employee::create(array_merge([
        'center_id' => $center->id,
        'full_name' => 'Attendance Tester '.$n,
        'mobile' => (string) (9800000000 + $n),
        'department' => 'Operations',
        'designation' => 'Executive',
        'joining_date' => '2026-01-01',
        'salary' => 25000,
        'base_location' => 'Pune',
        'daily_allowance' => 0,
        'travel_allowance' => 0,
        'status' => true,
    ], $overrides));
}

it('creates and edits attendance while calculating working hours', function () {
    $employee = makeAttendanceEmployee();
    $attendance = Attendance::create([
        'employee_id' => $employee->id,
        'attendance_date' => '2026-07-10',
        'punch_in_time' => '09:00',
        'punch_out_time' => '17:30',
        'attendance_status' => 'Present',
        'approval_status' => 'Pending',
    ]);

    expect($attendance->working_hours)->toBe('08:30')
        ->and($attendance->attendance_status)->toBe(AttendanceStatusCalculator::STATUS_PRESENT);

    $attendance->update(['punch_out_time' => '18:00']);
    expect($attendance->fresh()->working_hours)->toBe('09:00');
});

it('filters attendance records and supports approve and reject states', function () {
    $employee = makeAttendanceEmployee();
    $present = Attendance::create([
        'employee_id' => $employee->id,
        'attendance_date' => '2026-07-10',
        'punch_in_time' => '09:00',
        'punch_out_time' => '18:00',
        'attendance_status' => 'Present',
        'approval_status' => 'Pending',
    ]);
    $leave = Attendance::create([
        'employee_id' => $employee->id,
        'attendance_date' => '2026-07-11',
        'attendance_status' => 'Leave',
        'approval_status' => 'Pending',
    ]);

    expect(Attendance::query()->where('employee_id', $employee->id)->whereDate('attendance_date', '2026-07-10')->where('attendance_status', 'Present')->count())->toBe(1)
        ->and($leave->fresh()->attendance_status)->toBe('Leave');

    $present->update(['approval_status' => 'Approved', 'approved_by' => $employee->id]);
    $leave->update(['approval_status' => 'Rejected', 'approved_by' => $employee->id]);

    expect($present->fresh()->approval_status)->toBe('Approved')
        ->and($leave->fresh()->approval_status)->toBe('Rejected');
});

it('prevents duplicate attendance for one employee and date', function () {
    $employee = makeAttendanceEmployee();
    Attendance::create([
        'employee_id' => $employee->id,
        'attendance_date' => '2026-07-10',
        'punch_in_time' => '09:00',
        'attendance_status' => AttendanceStatusCalculator::STATUS_PUNCHED_IN,
        'approval_status' => 'Pending',
    ]);

    expect(fn () => Attendance::create([
        'employee_id' => $employee->id,
        'attendance_date' => '2026-07-10',
        'punch_in_time' => '10:00',
        'attendance_status' => AttendanceStatusCalculator::STATUS_PUNCHED_IN,
        'approval_status' => 'Pending',
    ]))->toThrow(QueryException::class);
});

it('marks punch in only as Punched In not Present', function () {
    $employee = makeAttendanceEmployee();

    $attendance = Attendance::create([
        'employee_id' => $employee->id,
        'attendance_date' => '2026-08-11',
        'punch_in_time' => '09:00:00',
        'attendance_status' => AttendanceStatusCalculator::STATUS_PUNCHED_IN,
        'approval_status' => 'Pending',
    ]);

    expect($attendance->attendance_status)->toBe(AttendanceStatusCalculator::STATUS_PUNCHED_IN)
        ->and($attendance->working_hours)->toBeNull();
});

it('calculates Present for exactly 8 hours and longer', function () {
    $calculator = app(AttendanceStatusCalculator::class);
    $employee = makeAttendanceEmployee();

    $eight = Attendance::create([
        'employee_id' => $employee->id,
        'attendance_date' => '2026-08-10',
        'punch_in_time' => '09:00:00',
        'punch_out_time' => '17:00:00',
        'approval_status' => 'Pending',
    ]);

    $nine = Attendance::create([
        'employee_id' => $employee->id,
        'attendance_date' => '2026-08-11',
        'punch_in_time' => '09:00:00',
        'punch_out_time' => '18:00:00',
        'approval_status' => 'Pending',
    ]);

    expect($eight->attendance_status)->toBe(AttendanceStatusCalculator::STATUS_PRESENT)
        ->and($nine->attendance_status)->toBe(AttendanceStatusCalculator::STATUS_PRESENT)
        ->and($calculator->calculate('09:00:00', '17:00:00', '2026-08-10'))->toBe(AttendanceStatusCalculator::STATUS_PRESENT)
        ->and($calculator->calculate('09:00:00', '18:00:00', '2026-08-10'))->toBe(AttendanceStatusCalculator::STATUS_PRESENT);
});

it('calculates Half Day for 4 hours up to under 8 hours', function () {
    $calculator = app(AttendanceStatusCalculator::class);
    $employee = makeAttendanceEmployee();

    $four = Attendance::create([
        'employee_id' => $employee->id,
        'attendance_date' => '2026-08-10',
        'punch_in_time' => '09:00:00',
        'punch_out_time' => '13:00:00',
        'approval_status' => 'Pending',
    ]);

    $six = Attendance::create([
        'employee_id' => $employee->id,
        'attendance_date' => '2026-08-11',
        'punch_in_time' => '09:00:00',
        'punch_out_time' => '15:00:00',
        'approval_status' => 'Pending',
    ]);

    expect($four->attendance_status)->toBe(AttendanceStatusCalculator::STATUS_HALF_DAY)
        ->and($six->attendance_status)->toBe(AttendanceStatusCalculator::STATUS_HALF_DAY)
        ->and($calculator->calculate('09:00:00', '12:59:00', '2026-08-10'))->toBe(AttendanceStatusCalculator::STATUS_ABSENT);
});

it('calculates Absent under 4 hours', function () {
    $employee = makeAttendanceEmployee();

    $attendance = Attendance::create([
        'employee_id' => $employee->id,
        'attendance_date' => '2026-08-10',
        'punch_in_time' => '09:00:00',
        'punch_out_time' => '12:59:00',
        'approval_status' => 'Pending',
    ]);

    expect($attendance->attendance_status)->toBe(AttendanceStatusCalculator::STATUS_ABSENT)
        ->and($attendance->total_working_minutes)->toBe(239);
});

it('does not count open punch today as Absent in monthly summary', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-11 12:00:00', AttendanceCalendar::TIMEZONE));

    $employee = makeAttendanceEmployee();

    // Completed prior working day with no attendance → Absent
    // Today punched in only → Punched In (excluded from absent)
    Attendance::create([
        'employee_id' => $employee->id,
        'attendance_date' => '2026-08-11',
        'punch_in_time' => '09:00:00',
        'approval_status' => 'Pending',
    ]);

    $summary = Attendance::adminEmployeeMonthlySummary($employee->id, 8, 2026);

    expect($summary['present_days'])->toBe(0)
        ->and($summary['half_days'])->toBe(0)
        ->and($summary['absent_days'])->toBeGreaterThan(0);

    // Today's open punch must not inflate absent beyond completed working days without attendance.
    $periodStart = Carbon::create(2026, 8, 1, 0, 0, 0, AttendanceCalendar::TIMEZONE)->startOfDay();
    $periodEnd = AttendanceCalendar::periodEndForMonth(8, 2026);
    $workingDays = AttendanceCalendar::workingDaysInPeriod($periodStart, $periodEnd);

    expect($summary['working_days'])->toBe($workingDays)
        ->and($summary['absent_days'])->toBe($workingDays - 1); // today punched-in excluded

    Carbon::setTestNow();
});

it('preserves Leave status and does not overwrite it from punches', function () {
    $employee = makeAttendanceEmployee();

    $attendance = Attendance::create([
        'employee_id' => $employee->id,
        'attendance_date' => '2026-08-10',
        'punch_in_time' => '09:00:00',
        'punch_out_time' => '18:00:00',
        'attendance_status' => 'Leave',
        'approval_status' => 'Pending',
    ]);

    expect($attendance->fresh()->attendance_status)->toBe('Leave');
});

function makeAttendanceApiUser(): array
{
    $employee = makeAttendanceEmployee();
    $user = User::create([
        'employee_id' => $employee->id,
        'name' => $employee->full_name,
        'email' => 'att'.$employee->id.'@test.local',
        'login_id' => $employee->mobile,
        'password' => Hash::make('Employee@123'),
        'role' => UserRole::Employee->value,
        'is_active' => true,
    ]);

    return [$user, $employee];
}

it('requires a photo for punch in and punch out', function () {
    Storage::fake('public');
    [$user, $employee] = makeAttendanceApiUser();

    $this->actingAs($user, 'sanctum')
        ->post('/api/attendance/punch-in', [
            'latitude' => 18.52,
            'longitude' => 73.85,
            'location_address' => 'Pune',
        ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['photo']);

    expect(Attendance::query()->where('employee_id', $employee->id)->exists())->toBeFalse();

    $this->actingAs($user, 'sanctum')
        ->post('/api/attendance/punch-in', [
            'latitude' => 18.52,
            'longitude' => 73.85,
            'location_address' => 'Pune',
            'photo' => UploadedFile::fake()->image('in.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    $this->actingAs($user, 'sanctum')
        ->post('/api/attendance/punch-out', [
            'latitude' => 18.53,
            'longitude' => 73.86,
            'location_address' => 'Pune Out',
        ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['photo']);

    $attendance = Attendance::query()->where('employee_id', $employee->id)->first();
    expect($attendance->punch_out_time)->toBeNull()
        ->and($attendance->punch_out_photo)->toBeNull();
});

it('stores punch in and punch out photos separately and returns public urls', function () {
    Storage::fake('public');
    [$user, $employee] = makeAttendanceApiUser();

    $inResponse = $this->actingAs($user, 'sanctum')
        ->post('/api/attendance/punch-in', [
            'latitude' => 18.5201234,
            'longitude' => 73.8509876,
            'location_address' => 'Pune In',
            'photo' => UploadedFile::fake()->image('in.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    expect($inResponse->json('data.in_photo'))->toContain('/storage/')
        ->and($inResponse->json('data.out_photo'))->toBeNull();

    $outResponse = $this->actingAs($user, 'sanctum')
        ->post('/api/attendance/punch-out', [
            'latitude' => 18.5301111,
            'longitude' => 73.8602222,
            'location_address' => 'Pune Out',
            'photo' => UploadedFile::fake()->image('out.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertOk();

    $attendance = Attendance::query()->where('employee_id', $employee->id)->first();
    expect($attendance->punch_in_photo)->not->toBeNull()
        ->and($attendance->punch_out_photo)->not->toBeNull()
        ->and($attendance->punch_in_photo)->not->toBe($attendance->punch_out_photo)
        ->and($outResponse->json('data.in_photo'))->toContain('/storage/')
        ->and($outResponse->json('data.out_photo'))->toContain('/storage/')
        ->and($outResponse->json('data.in_photo'))->not->toBe($outResponse->json('data.out_photo'))
        ->and($attendance->punchInMapsUrl())->toBe('https://www.google.com/maps?q='.$attendance->punch_in_latitude.','.$attendance->punch_in_longitude)
        ->and($attendance->punchOutMapsUrl())->toBe('https://www.google.com/maps?q='.$attendance->punch_out_latitude.','.$attendance->punch_out_longitude);

    Storage::disk('public')->assertExists($attendance->punch_in_photo);
    Storage::disk('public')->assertExists($attendance->punch_out_photo);
});

