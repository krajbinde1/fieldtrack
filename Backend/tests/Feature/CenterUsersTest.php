<?php

use App\Enums\CenterStaffRole;
use App\Enums\UserRole;
use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\OrgUsers\Pages\CreateOrgUser;
use App\Filament\Resources\OrgUsers\Pages\ListOrgUsers;
use App\Models\Center;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
});

it('lets a center manager create a user with login id and password from mobile', function () {
    $manager = User::query()->where('login_id', 'centermgr')->firstOrFail();
    $centerId = $manager->managedCenters()->first()?->id;
    expect($centerId)->not->toBeNull();

    Livewire::actingAs($manager)
        ->test(CreateEmployee::class)
        ->assertFormFieldDoesNotExist('login_id')
        ->assertFormFieldDoesNotExist('login_password')
        ->fillForm([
            'center_id' => $centerId,
            'full_name' => 'New Mobilizer',
            'mobile' => '9123456780',
            'email' => 'mobilizer@fieldtrack.local',
            'staff_role' => CenterStaffRole::Mobilizer->value,
            'status' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $employee = Employee::query()->where('mobile', '9123456780')->first();
    expect($employee)->not->toBeNull()
        ->and($employee->center_id)->toBe($centerId)
        ->and($employee->staff_role)->toBe(CenterStaffRole::Mobilizer->value)
        ->and($employee->created_by_user_id)->toBe($manager->id)
        ->and($employee->user?->login_id)->toBe('9123456780')
        ->and($employee->user?->role)->toBe(UserRole::Employee->value)
        ->and(Hash::check('6780', $employee->user?->password))->toBeTrue()
        ->and($employee->user?->getRawOriginal('password'))->not->toBe('6780');

    $this->postJson('/api/login', [
        'login_id' => '9123456780',
        'password' => '6780',
        'device_id' => 'device-staff',
    ])->assertOk()
        ->assertJsonPath('employee.staff_role', CenterStaffRole::Mobilizer->value)
        ->assertJsonPath('user.role', UserRole::Employee->value)
        ->assertJsonPath('user.login_id', '9123456780');
});

it('ignores a submitted custom login id and password when creating a center user', function () {
    $manager = User::query()->where('login_id', 'centermgr')->firstOrFail();
    $centerId = $manager->managedCenters()->first()?->id;

    Livewire::actingAs($manager)
        ->test(CreateEmployee::class)
        ->fillForm([
            'center_id' => $centerId,
            'full_name' => 'Housekeeper One',
            'mobile' => '9123456781',
            'email' => null,
            'staff_role' => CenterStaffRole::Housekeeper->value,
            'status' => true,
        ])
        ->set('data.login_id', 'field.mob-1')
        ->set('data.login_password', 'House@1234')
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('login_id', '9123456781')->first();
    expect($user)->not->toBeNull()
        ->and(User::query()->where('login_id', 'field.mob-1')->exists())->toBeFalse()
        ->and(Hash::check('6781', $user->password))->toBeTrue();

    $this->postJson('/api/login', [
        'login_id' => '9123456781',
        'password' => 'House@1234',
        'device_id' => 'device-ignored-password',
    ])->assertUnprocessable();

    $this->postJson('/api/login', [
        'login_id' => '9123456781',
        'password' => '6781',
        'device_id' => 'device-house',
    ])->assertOk()->assertJsonPath('user.login_id', '9123456781');
});

it('lets a center manager create a user from the mobile api with the same credentials', function () {
    $manager = User::query()->where('login_id', 'centermgr')->firstOrFail();
    $centerId = $manager->managedCenters()->first()?->id;

    $this->actingAs($manager, 'sanctum')
        ->postJson('/api/manager/employees', [
            'center_id' => $centerId,
            'full_name' => 'Api Mobilizer',
            'mobile' => '9876543219',
            'staff_role' => CenterStaffRole::Mobilizer->value,
            'login_id' => 'custom.api',
            'login_password' => 'ShouldIgnore@1',
        ])
        ->assertCreated()
        ->assertJsonPath('data.login_id', '9876543219')
        ->assertJsonPath('data.mobile', '9876543219');

    $created = User::query()->where('login_id', '9876543219')->first();
    expect($created)->not->toBeNull()
        ->and(Hash::check('3219', $created->password))->toBeTrue()
        ->and($created->getRawOriginal('password'))->not->toBe('3219');

    $this->postJson('/api/login', [
        'login_id' => '9876543219',
        'password' => '3219',
        'device_id' => 'device-api-staff',
    ])->assertOk()->assertJsonPath('user.login_id', '9876543219');
});

it('lets a center manager reset a user password to the last 4 digits of the current mobile', function () {
    $manager = User::query()->where('login_id', 'centermgr')->firstOrFail();
    $employee = Employee::query()->where('mobile', '9876543210')->firstOrFail();
    $loginId = $employee->user?->login_id;

    expect($loginId)->not->toBeNull()
        ->and(Hash::check('Employee@123', $employee->user?->password))->toBeTrue();

    $this->actingAs($manager, 'sanctum')
        ->postJson('/api/manager/employees/'.$employee->id.'/reset-password')
        ->assertOk()
        ->assertJsonPath('message', 'Password reset successfully. Default password: 3210')
        ->assertJsonPath('data.default_password', '3210')
        ->assertJsonPath('data.login_id', $loginId);

    expect(Hash::check('3210', $employee->user?->fresh()->password))->toBeTrue()
        ->and($employee->user?->fresh()->must_change_password)->toBeTrue();

    $this->postJson('/api/login', [
        'login_id' => $loginId,
        'password' => 'Employee@123',
        'device_id' => 'device-old-password',
    ])->assertUnprocessable();

    $this->postJson('/api/login', [
        'login_id' => $loginId,
        'password' => '3210',
        'device_id' => 'device-reset-password',
    ])->assertOk()->assertJsonPath('user.must_change_password', true);
});

it('does not change the password when a center manager updates the mobile number', function () {
    $manager = User::query()->where('login_id', 'centermgr')->firstOrFail();
    $employee = Employee::query()->where('mobile', '9876543210')->firstOrFail();
    $originalLoginId = $employee->user?->login_id;

    Livewire::actingAs($manager)
        ->test(EditEmployee::class, ['record' => $employee->getKey()])
        ->assertFormFieldDoesNotExist('login_password')
        ->assertActionExists('resetPassword')
        ->fillForm([
            'mobile' => '9123456790',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $employee->refresh()->load('user');
    expect($employee->mobile)->toBe('9123456790')
        ->and($employee->user?->login_id)->toBe($originalLoginId)
        ->and(Hash::check('Employee@123', $employee->user?->password))->toBeTrue();

    Livewire::actingAs($manager)
        ->test(EditEmployee::class, ['record' => $employee->getKey()])
        ->callAction('resetPassword')
        ->assertNotified('Password reset successfully. Default password: 6790');

    expect(Hash::check('6790', $employee->user?->fresh()->password))->toBeTrue()
        ->and(Hash::check('Employee@123', $employee->user?->fresh()->password))->toBeFalse()
        ->and($employee->user?->fresh()->login_id)->toBe($originalLoginId);
});

it('lets a center manager reset a password from the users table', function () {
    $manager = User::query()->where('login_id', 'centermgr')->firstOrFail();
    $employee = Employee::query()->where('mobile', '9876543210')->firstOrFail();

    Livewire::actingAs($manager)
        ->test(ListEmployees::class)
        ->callTableAction('resetPassword', $employee)
        ->assertNotified('Password reset successfully. Default password: 3210');

    expect(Hash::check('3210', $employee->user?->fresh()->password))->toBeTrue();
});

it('does not let a center manager reset another center user password', function () {
    $manager = User::query()->where('login_id', 'centermgr')->firstOrFail();
    $other = Employee::query()->where('mobile', '9876543211')->firstOrFail();

    $this->actingAs($manager, 'sanctum')
        ->postJson('/api/manager/employees/'.$other->id.'/reset-password')
        ->assertForbidden();

    expect(Hash::check('Employee@123', $other->user?->fresh()->password))->toBeTrue();
});

it('does not let a project head reset a center user password', function () {
    $projectHead = User::query()->where('login_id', 'projecthead')->firstOrFail();
    $employee = Employee::query()->where('mobile', '9876543210')->firstOrFail();

    $this->actingAs($projectHead, 'sanctum')
        ->postJson('/api/manager/employees/'.$employee->id.'/reset-password')
        ->assertForbidden();
});

it('does not let a center manager see or edit another center user', function () {
    $manager = User::query()->where('login_id', 'centermgr')->firstOrFail();
    $own = Employee::query()->where('mobile', '9876543210')->firstOrFail();
    $other = Employee::query()->where('mobile', '9876543211')->firstOrFail();

    Livewire::actingAs($manager)
        ->test(ListEmployees::class)
        ->assertCanSeeTableRecords([$own])
        ->assertCanNotSeeTableRecords([$other]);

    $this->actingAs($manager)
        ->get('/admin/center-users/'.$other->id.'/edit')
        ->assertNotFound();
});

it('does not let admin create operational center staff', function () {
    $admin = User::query()->where('login_id', 'admin')->firstOrFail();

    $this->actingAs($admin)->get('/admin/center-users')->assertForbidden();
    $this->actingAs($admin)->get('/admin/center-users/create')->assertForbidden();
});

it('lets admin create a director from the people users module', function () {
    $admin = User::query()->where('login_id', 'admin')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(CreateOrgUser::class)
        ->fillForm([
            'role' => UserRole::Director->value,
            'name' => 'Second Director',
            'login_id' => 'director2',
            'email' => 'director2@fieldtrack.local',
            'password' => 'Director@123',
            'is_active' => true,
            'must_change_password' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = User::query()->where('login_id', 'director2')->first();
    expect($created)->not->toBeNull()
        ->and($created->role)->toBe(UserRole::Director->value)
        ->and($created->directedProjects()->count())->toBe(0);
});

it('lets admin assign a project head to selected centers across projects', function () {
    $admin = User::query()->where('login_id', 'admin')->firstOrFail();
    $demoCenter = Center::query()->where('code', 'C1')->firstOrFail();
    $otherCenter = Center::query()->where('code', 'C9')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(CreateOrgUser::class)
        ->fillForm([
            'role' => UserRole::ProjectHead->value,
            'name' => 'PH Across Centers',
            'login_id' => 'phcenters',
            'email' => 'phcenters@fieldtrack.local',
            'password' => 'ProjectHead@123',
            'headedCenters' => [$demoCenter->id, $otherCenter->id],
            'is_active' => true,
            'must_change_password' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = User::query()->where('login_id', 'phcenters')->first();
    expect($created)->not->toBeNull()
        ->and($created->role)->toBe(UserRole::ProjectHead->value)
        ->and($created->headedCenters()->pluck('centers.id')->sort()->values()->all())
        ->toEqual(collect([$demoCenter->id, $otherCenter->id])->sort()->values()->all());
});

it('uses email as login id when admin leaves login id blank for a director', function () {
    $admin = User::query()->where('login_id', 'admin')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(CreateOrgUser::class)
        ->fillForm([
            'role' => UserRole::Director->value,
            'name' => 'Email Director',
            'login_id' => null,
            'email' => 'emaildir@fieldtrack.local',
            'password' => 'Director@123',
            'is_active' => true,
            'must_change_password' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = User::query()->where('email', 'emaildir@fieldtrack.local')->first();
    expect($created)->not->toBeNull()
        ->and($created->login_id)->toBe('emaildir@fieldtrack.local');

    $this->postJson('/api/login', [
        'login_id' => 'emaildir@fieldtrack.local',
        'password' => 'Director@123',
        'device_id' => 'device-email-dir',
    ])->assertOk()->assertJsonPath('user.login_id', 'emaildir@fieldtrack.local');
});

it('does not overwrite a custom org user login id when email changes', function () {
    $admin = User::query()->where('login_id', 'admin')->firstOrFail();

    $component = Livewire::actingAs($admin)
        ->test(CreateOrgUser::class)
        ->fillForm([
            'role' => UserRole::Director->value,
            'name' => 'Custom Login Director',
        ]);

    $component->set('data.email', 'syncme@fieldtrack.local')
        ->assertSet('data.login_id', 'syncme@fieldtrack.local');

    $component->set('data.login_id', 'custom.director')
        ->set('data.email', 'changed@fieldtrack.local')
        ->assertSet('data.login_id', 'custom.director');
});

it('rejects a duplicate login id on the people users module', function () {
    $admin = User::query()->where('login_id', 'admin')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(CreateOrgUser::class)
        ->fillForm([
            'role' => UserRole::Director->value,
            'name' => 'Duplicate Director',
            'login_id' => 'fielddirector',
            'email' => 'duplicate-dir@fieldtrack.local',
            'password' => 'Director@123',
            'is_active' => true,
            'must_change_password' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['login_id']);
});

it('lets a director see center managers from every center without a project assignment', function () {
    $director = User::query()->where('login_id', 'fielddirector')->firstOrFail();
    $own = User::query()->where('login_id', 'centermgr')->firstOrFail();
    $other = User::query()->where('login_id', 'othermgr')->firstOrFail();

    expect($director->directedProjects()->count())->toBe(0);

    Livewire::actingAs($director)
        ->test(ListOrgUsers::class)
        ->assertCanSeeTableRecords([$own, $other]);
});

it('shows assigned centers on the admin users list and can filter by center name', function () {
    $admin = User::query()->where('login_id', 'admin')->firstOrFail();
    $director = User::query()->where('login_id', 'fielddirector')->firstOrFail();
    $projectHead = User::query()->where('login_id', 'projecthead')->firstOrFail();
    $manager = User::query()->where('login_id', 'centermgr')->firstOrFail();
    $otherManager = User::query()->where('login_id', 'othermgr')->firstOrFail();
    $employee = User::query()->where('login_id', '9876543210')->firstOrFail();
    $demoCenterId = $manager->managedCenters()->first()?->id;

    expect($director->assignedCenterNames())->toBe([])
        ->and($admin->assignedCenterNames())->toBe([])
        ->and($projectHead->assignedCenterNames())->toBe(['Demo Center'])
        ->and($manager->assignedCenterNames())->toBe(['Demo Center'])
        ->and($otherManager->assignedCenterNames())->toBe(['Other Center'])
        ->and($employee->assignedCenterNames())->toBe(['Demo Center'])
        ->and($demoCenterId)->not->toBeNull();

    Livewire::actingAs($admin)
        ->test(ListOrgUsers::class)
        ->assertSee('Center')
        ->assertSee('Demo Center')
        ->assertSee('Other Center')
        ->assertCanSeeTableRecords([$director, $projectHead, $manager, $otherManager])
        ->filterTable('center', $demoCenterId)
        ->assertCanSeeTableRecords([$projectHead, $manager])
        ->assertCanNotSeeTableRecords([$otherManager, $director]);
});
