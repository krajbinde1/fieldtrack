<?php

use App\Enums\UserRole;
use App\Filament\Auth\Login;
use App\Filament\Pages\AccountSettings;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
});

it('lets an admin open account settings', function () {
    $admin = User::query()->where('login_id', 'director')->firstOrFail();

    $this->actingAs($admin)->get('/admin/account-settings')->assertOk();
});

it('lets a director open account settings', function () {
    $director = User::query()->where('login_id', 'fielddirector')->firstOrFail();

    $this->actingAs($director)->get('/admin/account-settings')->assertOk();
});

it('blocks project head and center manager from account settings', function () {
    $projectHead = User::query()->where('login_id', 'projecthead')->firstOrFail();
    $this->actingAs($projectHead)->get('/admin/account-settings')->assertForbidden();
});

it('blocks a center manager from account settings', function () {
    $centerManager = User::query()->where('login_id', 'centermgr')->firstOrFail();
    $this->actingAs($centerManager)->get('/admin/account-settings')->assertForbidden();
});

it('requires the current password before saving account settings', function () {
    $admin = User::query()->where('login_id', 'director')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(AccountSettings::class)
        ->fillForm([
            'login_id' => 'newadmin',
            'currentPassword' => 'WrongPassword@1',
            'password' => 'NewAdmin@123',
            'passwordConfirmation' => 'NewAdmin@123',
        ])
        ->call('save')
        ->assertHasFormErrors(['currentPassword']);

    expect(User::query()->find($admin->id)?->login_id)->toBe('director');
});

it('rejects a login id that is already taken', function () {
    $admin = User::query()->where('login_id', 'director')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(AccountSettings::class)
        ->fillForm([
            'login_id' => 'fielddirector',
            'currentPassword' => 'Director@123',
        ])
        ->call('save')
        ->assertHasFormErrors(['login_id']);

    expect(User::query()->find($admin->id)?->login_id)->toBe('director');
});

it('updates the existing admin account then logs out so the new credentials work', function () {
    $admin = User::query()->where('login_id', 'director')->firstOrFail();
    $adminId = $admin->id;
    $role = $admin->role;
    $userCount = User::query()->count();
    $adminCount = User::query()->where('role', UserRole::Admin->value)->count();

    Livewire::actingAs($admin)
        ->test(AccountSettings::class)
        ->fillForm([
            'login_id' => 'fieldadmin',
            'currentPassword' => 'Director@123',
            'password' => 'NewAdmin@123',
            'passwordConfirmation' => 'NewAdmin@123',
        ])
        ->call('save')
        ->assertRedirect('/admin/login');

    $this->app['auth']->forgetGuards();
    $this->assertGuest();

    $updated = User::query()->findOrFail($adminId);
    expect(User::query()->count())->toBe($userCount)
        ->and(User::query()->where('role', UserRole::Admin->value)->count())->toBe($adminCount)
        ->and($updated->role)->toBe($role)
        ->and($updated->login_id)->toBe('fieldadmin')
        ->and(Hash::check('NewAdmin@123', $updated->password))->toBeTrue()
        ->and(Hash::check('Director@123', $updated->password))->toBeFalse();

    Livewire::test(Login::class)
        ->fillForm([
            'login_id' => 'fieldadmin',
            'password' => 'NewAdmin@123',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $this->assertAuthenticatedAs($updated);
});

it('lets a director change only the login id on their existing account', function () {
    $director = User::query()->where('login_id', 'fielddirector')->firstOrFail();
    $directorId = $director->id;
    $userCount = User::query()->count();

    Livewire::actingAs($director)
        ->test(AccountSettings::class)
        ->fillForm([
            'login_id' => 'opsdirector',
            'currentPassword' => 'Director@123',
        ])
        ->call('save')
        ->assertRedirect('/admin/login');

    $this->app['auth']->forgetGuards();
    $this->assertGuest();

    $updated = User::query()->findOrFail($directorId);
    expect(User::query()->count())->toBe($userCount)
        ->and($updated->role)->toBe(UserRole::Director->value)
        ->and($updated->login_id)->toBe('opsdirector')
        ->and(Hash::check('Director@123', $updated->password))->toBeTrue();

    Livewire::test(Login::class)
        ->fillForm([
            'login_id' => 'opsdirector',
            'password' => 'Director@123',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect();
});
