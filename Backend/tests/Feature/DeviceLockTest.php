<?php

use App\Enums\UserRole;
use App\Exceptions\DeviceRegisteredException;
use App\Filament\Pages\DeviceManagement;
use App\Models\User;
use App\Services\Auth\MobileSessionService;
use Livewire\Livewire;

function deviceLockedUser(UserRole $role): User
{
    if ($role === UserRole::Employee) {
        return seedOrg()['userA'];
    }

    return User::factory()->create([
        'name' => $role->label().' User',
        'login_id' => 'lock-'.$role->value,
        'password' => 'Employee@123',
        'role' => $role->value,
        'is_active' => true,
    ]);
}

it('binds the first mobile device and allows the same device to log in again', function () {
    $user = seedOrg()['userA'];

    $this->postJson('/api/login', [
        'login_id' => $user->login_id,
        'password' => 'Employee@123',
        'device_id' => 'device-a',
    ])->assertOk()->assertJsonPath('success', true);

    expect($user->fresh()->active_mobile_device_id)->toBe('device-a');

    $this->postJson('/api/login', [
        'login_id' => $user->login_id,
        'password' => 'Employee@123',
        'device_id' => 'device-a',
    ])->assertOk()->assertJsonPath('success', true);

    expect($user->fresh()->active_mobile_device_id)->toBe('device-a');
});

it('blocks login from another device until admin resets it', function (UserRole $role) {
    $user = deviceLockedUser($role);

    $this->postJson('/api/login', [
        'login_id' => $user->login_id,
        'password' => 'Employee@123',
        'device_id' => 'device-a',
    ])->assertOk();

    $this->postJson('/api/login', [
        'login_id' => $user->login_id,
        'password' => 'Employee@123',
        'device_id' => 'device-b',
    ])->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', DeviceRegisteredException::CODE)
        ->assertJsonPath('message', DeviceRegisteredException::MESSAGE);

    expect($user->fresh()->active_mobile_device_id)->toBe('device-a');
})->with([
    UserRole::Employee,
    UserRole::CenterManager,
    UserRole::ProjectHead,
    UserRole::Director,
]);

it('keeps the registered device after logout so another phone stays blocked', function () {
    $user = seedOrg()['userA'];

    $login = $this->postJson('/api/login', [
        'login_id' => $user->login_id,
        'password' => 'Employee@123',
        'device_id' => 'device-a',
    ])->assertOk();

    $this->withToken($login->json('token'))
        ->postJson('/api/logout')
        ->assertOk();

    expect($user->fresh()->active_mobile_device_id)->toBe('device-a')
        ->and($user->fresh()->active_mobile_session_id)->toBeNull();

    $this->postJson('/api/login', [
        'login_id' => $user->login_id,
        'password' => 'Employee@123',
        'device_id' => 'device-b',
    ])->assertForbidden()
        ->assertJsonPath('message', DeviceRegisteredException::MESSAGE);

    $this->postJson('/api/login', [
        'login_id' => $user->login_id,
        'password' => 'Employee@123',
        'device_id' => 'device-a',
    ])->assertOk();
});

it('lets admin reset the device so the next login registers a new phone', function () {
    $user = seedOrg()['userA'];

    $old = $this->postJson('/api/login', [
        'login_id' => $user->login_id,
        'password' => 'Employee@123',
        'device_id' => 'device-old',
    ])->assertOk();

    $oldToken = $old->json('token');

    app(MobileSessionService::class)->resetDevice($user->fresh());

    expect($user->fresh()->active_mobile_device_id)->toBeNull();

    $this->withToken($oldToken)
        ->getJson('/api/me')
        ->assertUnauthorized();

    $this->postJson('/api/login', [
        'login_id' => $user->login_id,
        'password' => 'Employee@123',
        'device_id' => 'device-new',
    ])->assertOk();

    expect($user->fresh()->active_mobile_device_id)->toBe('device-new');

    $this->postJson('/api/login', [
        'login_id' => $user->login_id,
        'password' => 'Employee@123',
        'device_id' => 'device-old',
    ])->assertForbidden();
});

it('does not device-lock admin web login', function () {
    $this->seed();
    $admin = User::query()->where('login_id', 'director')->firstOrFail();
    $admin->forceFill(['active_mobile_device_id' => 'phone-admin'])->save();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk();
});

it('does not device-lock admin mobile login onto a single phone', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin->value,
        'login_id' => 'admin-mobile',
        'password' => 'Director@123',
        'is_active' => true,
    ]);

    $this->postJson('/api/login', [
        'login_id' => 'admin-mobile',
        'password' => 'Director@123',
        'device_id' => 'admin-phone-1',
    ])->assertOk();

    $this->postJson('/api/login', [
        'login_id' => 'admin-mobile',
        'password' => 'Director@123',
        'device_id' => 'admin-phone-2',
    ])->assertOk();

    expect($admin->fresh()->active_mobile_device_id)->toBe('admin-phone-2');
});

it('allows only admin to open device management and reset a device', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin->value]);
    $director = User::factory()->create(['role' => UserRole::Director->value]);
    $employee = seedOrg()['userA'];
    $employee->forceFill(['active_mobile_device_id' => 'device-to-reset'])->save();

    Livewire::actingAs($admin)
        ->test(DeviceManagement::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$employee->fresh(), $director])
        ->callTableAction('resetDevice', $employee);

    expect($employee->fresh()->active_mobile_device_id)->toBeNull();

    Livewire::actingAs($director)
        ->test(DeviceManagement::class)
        ->assertForbidden();
});
