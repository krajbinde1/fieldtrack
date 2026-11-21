<?php

use App\Enums\UserRole;
use App\Filament\Auth\Login;
use App\Filament\Resources\Centers\Pages\CreateCenter;
use App\Filament\Resources\Centers\Pages\EditCenter;
use App\Filament\Resources\Centers\Pages\ListCenters;
use App\Models\Center;
use App\Models\User;
use App\Services\CenterLoginService;
use App\Services\OrganizationAccessService;
use App\Support\CenterLoginIdGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

it('builds a stable unique snake_case center login id', function () {
    $org = seedOrg();

    expect(CenterLoginIdGenerator::fromName('Khultabad 4.0'))->toBe('khultabad_4_0')
        ->and(CenterLoginIdGenerator::fromName('  __Hello---World!! '))->toBe('hello_world');

    User::query()->create([
        'name' => 'Taken',
        'email' => 'taken.name@fieldtrack.local',
        'login_id' => 'taken_name',
        'password' => Hash::make('Taken@123'),
        'role' => UserRole::Employee->value,
        'is_active' => true,
    ]);

    $taken = Center::query()->create([
        'scheme_id' => $org['projectA']->id,
        'name' => 'Taken Name',
        'code' => 'TN1',
        'is_active' => true,
    ]);
    $first = Center::query()->create([
        'scheme_id' => $org['projectA']->id,
        'name' => 'Khultabad 4.0',
        'code' => 'KH1',
        'is_active' => true,
    ]);
    $second = Center::query()->create([
        'scheme_id' => $org['projectA']->id,
        'name' => 'Khultabad 4.0',
        'code' => 'KH2',
        'is_active' => true,
    ]);

    expect($taken->login_id)->toBe('taken_name_2')
        ->and($first->login_id)->toBe('khultabad_4_0')
        ->and($second->login_id)->toBe('khultabad_4_0_2');

    $first->update([
        'name' => 'Khultabad Renamed',
        'login_id' => 'hacked_login',
    ]);

    expect($first->fresh()->login_id)->toBe('khultabad_4_0')
        ->and($first->fresh()->name)->toBe('Khultabad Renamed');

    expect(fn () => Center::query()->insert([
        'scheme_id' => $org['projectA']->id,
        'name' => 'Duplicate Login',
        'code' => 'KH3',
        'login_id' => 'khultabad_4_0',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('creates a center login from the primary manager mobile without changing that manager', function () {
    $org = seedOrg();
    $manager = managerWithMobile('9123456701', 'Primary CM', 'primarycm@fieldtrack.local');
    $managerPassword = $manager->getRawOriginal('password');

    Livewire::actingAs($org['admin'])
        ->test(CreateCenter::class)
        ->fillForm([
            'scheme_id' => $org['projectA']->id,
            'name' => 'Khultabad 4.0',
            'is_active' => true,
            'centerManagers' => [$manager->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $center = Center::query()->where('name', 'Khultabad 4.0')->firstOrFail();
    $account = $center->loginUser;

    expect($center->login_id)->toBe('khultabad_4_0')
        ->and($center->primary_center_manager_id)->toBe($manager->id)
        ->and($account)->not->toBeNull()
        ->and($account->is_center_login)->toBeTrue()
        ->and($account->role)->toBe(UserRole::CenterManager->value)
        ->and($account->login_id)->toBe('khultabad_4_0')
        ->and($account->id)->not->toBe($manager->id)
        ->and(Hash::check('6701', $account->password))->toBeTrue()
        ->and($account->getRawOriginal('password'))->not->toBe('6701')
        ->and($manager->fresh()->getRawOriginal('password'))->toBe($managerPassword)
        ->and($manager->fresh()->login_id)->toBe('9123456701')
        ->and($center->centerManagers()->pluck('users.id')->all())->toBe([$manager->id]);

    Livewire::actingAs($org['admin'])
        ->test(EditCenter::class, ['record' => $center->getKey()])
        ->assertFormSet(['login_id' => 'khultabad_4_0'])
        ->assertSee('Center Login ID')
        ->assertDontSee('Default password')
        ->fillForm(['name' => 'Khultabad Renamed'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($center->fresh()->login_id)->toBe('khultabad_4_0')
        ->and($center->fresh()->loginUser->login_id)->toBe('khultabad_4_0')
        ->and($center->fresh()->loginUser->name)->toBe('Khultabad Renamed')
        ->and(Hash::check('6701', $center->fresh()->loginUser->password))->toBeTrue()
        ->and($manager->fresh()->getRawOriginal('password'))->toBe($managerPassword);
});

it('requires one active primary manager when a center has several', function () {
    $org = seedOrg();
    $first = managerWithMobile('9123456701', 'First CM', 'firstcm@fieldtrack.local');
    $second = managerWithMobile('9123456702', 'Second CM', 'secondcm@fieldtrack.local');
    $firstPassword = $first->getRawOriginal('password');
    $secondPassword = $second->getRawOriginal('password');

    Livewire::actingAs($org['admin'])
        ->test(CreateCenter::class)
        ->fillForm([
            'scheme_id' => $org['projectA']->id,
            'name' => 'Two Manager Center',
            'is_active' => true,
            'centerManagers' => [$first->id, $second->id],
        ])
        ->call('create')
        ->assertHasFormErrors(['primary_center_manager_id']);

    expect(Center::query()->where('name', 'Two Manager Center')->exists())->toBeFalse();

    Livewire::actingAs($org['admin'])
        ->test(CreateCenter::class)
        ->fillForm([
            'scheme_id' => $org['projectA']->id,
            'name' => 'Two Manager Center',
            'is_active' => true,
            'centerManagers' => [$first->id, $second->id],
            'primary_center_manager_id' => $second->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $center = Center::query()->where('name', 'Two Manager Center')->firstOrFail();

    expect($center->primary_center_manager_id)->toBe($second->id)
        ->and(Hash::check('6702', $center->loginUser->password))->toBeTrue()
        ->and(Hash::check('6701', $center->loginUser->password))->toBeFalse()
        ->and($first->fresh()->getRawOriginal('password'))->toBe($firstPassword)
        ->and($second->fresh()->getRawOriginal('password'))->toBe($secondPassword);
});

it('resets the center password for admins only and does not reveal it', function () {
    $org = seedOrg();
    $manager = managerWithMobile('9123456701', 'Primary CM', 'primarycm@fieldtrack.local');
    $managerPassword = $manager->getRawOriginal('password');
    $center = centerWithManager($org['projectA']->id, 'Reset Center', $manager);
    $account = $center->loginUser;
    $account->forceFill(['password' => 'replaced-secret'])->save();

    $org['projectHead']->headedCenters()->attach($center->id);

    Livewire::actingAs($org['projectHead'])
        ->test(EditCenter::class, ['record' => $center->getKey()])
        ->assertActionHidden('resetCenterPassword')
        ->assertDontSee('Reset Password');

    expect(Hash::check('replaced-secret', $account->fresh()->password))->toBeTrue()
        ->and($manager->fresh()->getRawOriginal('password'))->toBe($managerPassword);

    Livewire::actingAs($org['centerManager'])
        ->test(EditCenter::class, ['record' => $org['centerA']->getKey()])
        ->assertForbidden();

    Livewire::actingAs($org['admin'])
        ->test(EditCenter::class, ['record' => $center->getKey()])
        ->assertActionVisible('resetCenterPassword')
        ->assertDontSee('Default password')
        ->callAction('resetCenterPassword')
        ->assertNotified('Center password was reset.');

    expect(Hash::check('6701', $account->fresh()->password))->toBeTrue()
        ->and(Hash::check('replaced-secret', $account->fresh()->password))->toBeFalse()
        ->and($account->fresh()->getRawOriginal('password'))->not->toBe('6701')
        ->and($manager->fresh()->getRawOriginal('password'))->toBe($managerPassword)
        ->and($manager->fresh()->login_id)->toBe('9123456701');
});

it('blocks center login creation and reset without a usable primary mobile', function () {
    $org = seedOrg();
    $managerPassword = $org['centerManager']->getRawOriginal('password');

    Livewire::actingAs($org['admin'])
        ->test(CreateCenter::class)
        ->fillForm([
            'scheme_id' => $org['projectA']->id,
            'name' => 'No Mobile Center',
            'is_active' => true,
            'centerManagers' => [$org['centerManager']->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $center = Center::query()->where('name', 'No Mobile Center')->firstOrFail();

    expect($center->login_id)->toBe('no_mobile_center')
        ->and($center->login_user_id)->toBeNull()
        ->and($center->primary_center_manager_id)->toBe($org['centerManager']->id);

    try {
        app(CenterLoginService::class)->resetPassword($center);
        $this->fail('Center login reset should have been blocked.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('primary_center_manager_id')
            ->and(json_encode($exception->errors()))->not->toContain('6701');
    }

    expect($center->fresh()->login_user_id)->toBeNull()
        ->and($org['centerManager']->fresh()->getRawOriginal('password'))->toBe($managerPassword);

    $inactive = managerWithMobile('9123456799', 'Inactive CM', 'inactivecm@fieldtrack.local');
    $inactive->forceFill(['is_active' => false])->save();
    $active = managerWithMobile('9123456701', 'Active CM', 'activecm@fieldtrack.local');
    $activePassword = $active->getRawOriginal('password');
    $ready = centerWithManager($org['projectA']->id, 'Inactive Primary Center', $active);
    $readyPassword = $ready->loginUser->getRawOriginal('password');
    $ready->centerManagers()->sync([$active->id, $inactive->id]);
    $ready->update(['primary_center_manager_id' => $inactive->id]);

    try {
        app(CenterLoginService::class)->resetPassword($ready->fresh());
        $this->fail('An inactive manager cannot reset the center login.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('primary_center_manager_id');
    }

    expect($ready->fresh()->loginUser->getRawOriginal('password'))->toBe($readyPassword)
        ->and($active->fresh()->getRawOriginal('password'))->toBe($activePassword);
});

it('signs in with the center login id through the existing login and only sees that center', function () {
    $org = seedOrg();
    $manager = managerWithMobile('9123456701', 'Primary CM', 'primarycm@fieldtrack.local');
    $center = centerWithManager($org['projectA']->id, 'Khultabad 4.0', $manager);
    $account = $center->loginUser;

    Livewire::test(Login::class)
        ->fillForm([
            'login_id' => 'khultabad_4_0',
            'password' => '6701',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $this->assertAuthenticatedAs($account);

    $login = $this->postJson('/api/login', [
        'login_id' => 'khultabad_4_0',
        'password' => '6701',
        'device_id' => 'center-device-1',
    ])->assertOk();

    expect($login->json('user'))->not->toHaveKey('password')
        ->and($login->json('user.login_id'))->toBe('khultabad_4_0')
        ->and($login->json('user.id'))->not->toBe($manager->id)
        ->and($login->getContent())->not->toContain('6701');

    expect(app(OrganizationAccessService::class)->visibleCenterIds($account))->toBe([$center->id]);

    Sanctum::actingAs($account);

    $this->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonPath('data.centers', 1);

    $this->getJson('/api/dashboard?center_id='.$org['centerB']->id)->assertForbidden();

    $centers = collect($this->getJson('/api/manager/centers')->assertOk()->json('data'))->pluck('name')->all();
    expect($centers)->toBe(['Khultabad 4.0']);

    Livewire::actingAs($account)
        ->test(ListCenters::class)
        ->assertCanSeeTableRecords([$center])
        ->assertCanNotSeeTableRecords([$org['centerA'], $org['centerB']]);

    $this->actingAs($account)->get('/admin/users')->assertForbidden();

    $this->postJson('/api/login', [
        'login_id' => '9123456701',
        'password' => 'Manager@123',
        'device_id' => 'manager-device-1',
    ])->assertOk()->assertJsonPath('user.id', $manager->id);
});

it('backfills login ids and skips centers that cannot have a password yet', function () {
    $org = seedOrg();
    $manager = managerWithMobile('9123456701', 'Primary CM', 'primarycm@fieldtrack.local');
    $managerPassword = $manager->getRawOriginal('password');

    $ready = Center::query()->create([
        'scheme_id' => $org['projectA']->id,
        'name' => 'Backfill Center',
        'code' => 'BF1',
        'is_active' => true,
    ]);
    $lonely = Center::query()->create([
        'scheme_id' => $org['projectA']->id,
        'name' => 'Lonely Center',
        'code' => 'LN1',
        'is_active' => true,
    ]);
    $ambiguous = Center::query()->create([
        'scheme_id' => $org['projectA']->id,
        'name' => 'Ambiguous Center',
        'code' => 'AM1',
        'is_active' => true,
    ]);
    $second = managerWithMobile('9123456702', 'Second CM', 'secondcm@fieldtrack.local');

    Center::query()->whereKey($ready->id)->update(['login_id' => null]);
    $manager->managedCenters()->attach($ready->id);
    $ambiguous->centerManagers()->sync([$manager->id, $second->id]);

    app(CenterLoginService::class)->backfill();

    $ready->refresh();
    $account = $ready->loginUser;

    expect($ready->login_id)->toBe('backfill_center')
        ->and($ready->primary_center_manager_id)->toBe($manager->id)
        ->and($account)->not->toBeNull()
        ->and(Hash::check('6701', $account->password))->toBeTrue()
        ->and($lonely->fresh()->login_id)->toBe('lonely_center')
        ->and($lonely->fresh()->login_user_id)->toBeNull()
        ->and($ambiguous->fresh()->login_id)->toBe('ambiguous_center')
        ->and($ambiguous->fresh()->login_user_id)->toBeNull()
        ->and($ambiguous->fresh()->primary_center_manager_id)->toBeNull()
        ->and($manager->fresh()->getRawOriginal('password'))->toBe($managerPassword);

    $account->forceFill(['password' => 'custom-pass-99'])->save();
    app(CenterLoginService::class)->backfill();

    expect(Hash::check('custom-pass-99', $account->fresh()->password))->toBeTrue()
        ->and($manager->fresh()->getRawOriginal('password'))->toBe($managerPassword);
});

function managerWithMobile(string $mobile, string $name, string $email): User
{
    return User::query()->create([
        'name' => $name,
        'email' => $email,
        'login_id' => $mobile,
        'password' => Hash::make('Manager@123'),
        'role' => UserRole::CenterManager->value,
        'is_active' => true,
    ]);
}

function centerWithManager(int $schemeId, string $name, User $manager): Center
{
    $center = Center::query()->create([
        'scheme_id' => $schemeId,
        'name' => $name,
        'is_active' => true,
    ]);
    $center->centerManagers()->sync([$manager->id]);
    $center->forceFill(['primary_center_manager_id' => $manager->id])->save();
    app(CenterLoginService::class)->syncAccount($center);

    return $center->fresh(['loginUser']);
}
