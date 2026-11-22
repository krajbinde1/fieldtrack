<?php

use App\Enums\CenterStaffRole;
use App\Enums\FieldActivityType;
use App\Enums\UserRole;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Models\Attendance;
use App\Models\Center;
use App\Models\Employee;
use App\Models\FieldActivity;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

function makeDeletableCenterUser(Center $center, string $mobile, string $name = 'Deletable User'): array
{
    $employee = Employee::create([
        'center_id' => $center->id,
        'full_name' => $name,
        'mobile' => $mobile,
        'email' => $mobile.'@test.local',
        'status' => true,
        'staff_role' => CenterStaffRole::Mobilizer->value,
    ]);

    $user = User::create([
        'employee_id' => $employee->id,
        'name' => $name,
        'email' => $mobile.'@users.test',
        'login_id' => $mobile,
        'password' => Hash::make('Employee@123'),
        'role' => UserRole::Employee->value,
        'is_active' => true,
    ]);

    return [$employee, $user];
}

it('lets a center manager permanently delete a user with no related records', function () {
    $org = seedOrg();
    [$employee, $user] = makeDeletableCenterUser($org['centerA'], '9111111101');
    $keptAttendance = Attendance::query()->create([
        'employee_id' => $org['empA']->id,
        'attendance_date' => '2026-11-20',
        'punch_in_time' => '09:00:00',
    ]);

    $this->actingAs($org['centerManager']);

    expect(EmployeeResource::canDelete($employee))->toBeTrue()
        ->and(Employee::DELETE_BLOCKED_MESSAGE)->toBe('Cannot delete this user because related records exist.');

    Livewire::actingAs($org['centerManager'])
        ->test(ListEmployees::class)
        ->assertTableActionVisible('edit', $employee)
        ->assertTableActionVisible('resetPassword', $employee)
        ->assertTableActionVisible('delete', $employee)
        ->assertTableActionHasIcon('delete', 'heroicon-o-trash', $employee)
        ->assertTableActionHasColor('delete', 'danger', $employee)
        ->assertTableActionHasLabel('delete', 'Delete', $employee)
        ->callTableAction('delete', $employee)
        ->assertNotified('User deleted.')
        ->assertCanNotSeeTableRecords([$employee]);

    expect(Employee::withTrashed()->find($employee->id))->toBeNull()
        ->and(User::query()->find($user->id))->toBeNull()
        ->and($org['empA']->fresh())->not->toBeNull()
        ->and($org['userA']->fresh())->not->toBeNull()
        ->and($org['empB']->fresh())->not->toBeNull()
        ->and(Attendance::query()->find($keptAttendance->id))->not->toBeNull()
        ->and($org['centerA']->fresh())->not->toBeNull();
});

it('blocks deletion when attendance or another related record exists', function () {
    $org = seedOrg();
    [$withAttendance, $attendanceUser] = makeDeletableCenterUser($org['centerA'], '9111111102', 'Attendance User');
    $attendance = Attendance::query()->create([
        'employee_id' => $withAttendance->id,
        'attendance_date' => '2026-11-20',
        'punch_in_time' => '09:00:00',
    ]);

    [$withActivity, $activityUser] = makeDeletableCenterUser($org['centerA'], '9111111103', 'Activity User');
    $activity = FieldActivity::query()->create([
        'employee_id' => $withActivity->id,
        'user_id' => $activityUser->id,
        'center_id' => $org['centerA']->id,
        'scheme_id' => $org['projectA']->id,
        'activity_type' => FieldActivityType::Other,
        'activity_name' => 'Village Visit',
        'activity_at' => now(),
        'photo_path' => 'field-activities/keep.jpg',
        'latitude' => 18.52,
        'longitude' => 73.85,
    ]);

    $this->actingAs($org['centerManager']);

    Livewire::actingAs($org['centerManager'])
        ->test(ListEmployees::class)
        ->callTableAction('delete', $withAttendance)
        ->assertNotified('Cannot delete this user because related records exist.')
        ->assertCanSeeTableRecords([$withAttendance])
        ->callTableAction('delete', $withActivity)
        ->assertNotified('Cannot delete this user because related records exist.')
        ->assertCanSeeTableRecords([$withActivity]);

    expect(Employee::query()->find($withAttendance->id))->not->toBeNull()
        ->and(User::query()->find($attendanceUser->id))->not->toBeNull()
        ->and(Attendance::withTrashed()->find($attendance->id))->not->toBeNull()
        ->and(Attendance::withTrashed()->find($attendance->id)?->employee_id)->toBe($withAttendance->id)
        ->and(Employee::query()->find($withActivity->id))->not->toBeNull()
        ->and(User::query()->find($activityUser->id))->not->toBeNull()
        ->and(FieldActivity::query()->find($activity->id))->not->toBeNull()
        ->and(FieldActivity::query()->find($activity->id)?->employee_id)->toBe($withActivity->id)
        ->and($withAttendance->fresh()->deleteIfUnused())->toBeFalse()
        ->and(Attendance::query()->find($attendance->id))->not->toBeNull();
});

it('does not let a center manager delete a user outside assigned centers', function () {
    $org = seedOrg();
    $manager = $org['centerManager'];
    $other = $org['empB'];
    $otherUserId = User::query()->where('employee_id', $other->id)->value('id');

    $this->actingAs($manager);

    expect(EmployeeResource::canDelete($other))->toBeFalse();

    try {
        EmployeeResource::deleteManagedEmployee($other);
        throw new RuntimeException('Deletion outside assigned centers should be refused.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }

    Livewire::actingAs($manager)
        ->test(ListEmployees::class)
        ->assertCanNotSeeTableRecords([$other]);

    try {
        Livewire::actingAs($manager)
            ->test(ListEmployees::class)
            ->callTableAction('delete', $other);
    } catch (Throwable) {
        // The center-scoped list does not include this user, so the action cannot run.
    }

    expect(Employee::query()->find($other->id))->not->toBeNull()
        ->and(User::query()->find($otherUserId))->not->toBeNull();
});

it('does not let a center manager delete an admin, director, coordinator, center manager, or themselves', function () {
    $org = seedOrg();
    $manager = $org['centerManager'];

    $coordinator = User::query()->create([
        'name' => 'Protected Coordinator',
        'email' => 'coord-protected@test.local',
        'login_id' => 'coordprotected',
        'password' => Hash::make('secret'),
        'role' => UserRole::CenterCoordinator->value,
        'is_active' => true,
    ]);
    $otherManager = User::query()->create([
        'name' => 'Protected Manager',
        'email' => 'mgr-protected@test.local',
        'login_id' => 'mgrprotected',
        'password' => Hash::make('secret'),
        'role' => UserRole::CenterManager->value,
        'is_active' => true,
    ]);

    $protected = [
        'admin' => [$org['admin'], '9111111104'],
        'director' => [$org['director'], '9111111105'],
        'coordinator' => [$coordinator, '9111111106'],
        'center manager' => [$otherManager, '9111111107'],
        'self' => [$manager, '9111111108'],
    ];

    $this->actingAs($manager);

    foreach ($protected as [$account, $mobile]) {
        $employee = Employee::query()->create([
            'center_id' => $org['centerA']->id,
            'full_name' => $account->name,
            'mobile' => $mobile,
            'status' => true,
            'staff_role' => CenterStaffRole::Mobilizer->value,
        ]);
        $account->forceFill(['employee_id' => $employee->id])->save();

        expect(EmployeeResource::canDelete($employee->fresh()))->toBeFalse();

        try {
            EmployeeResource::deleteManagedEmployee($employee->fresh());
            throw new RuntimeException('Deletion of a protected user should be refused.');
        } catch (HttpException $exception) {
            expect($exception->getStatusCode())->toBe(403);
        }

        expect(Employee::query()->find($employee->id))->not->toBeNull()
            ->and(User::query()->find($account->id))->not->toBeNull()
            ->and(User::query()->find($account->id)?->role)->toBe($account->role);
    }
});
