<?php

use App\Enums\UserRole;
use App\Filament\Auth\Login;
use App\Filament\Pages\ChangePassword;
use App\Filament\Resources\Centers\Pages\CreateCenter;
use App\Filament\Resources\Centers\Pages\EditCenter;
use App\Models\Center;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('assigns multiple coordinators on create and leaves center managers for edit', function () {
    $org = seedOrg();
    $first = User::query()->create([
        'name' => 'Coord One',
        'email' => 'coordone@fieldtrack.local',
        'login_id' => '9123456711',
        'password' => Hash::make('6711'),
        'role' => UserRole::CenterCoordinator->value,
        'is_active' => true,
        'is_center_login' => false,
    ]);
    $second = User::query()->create([
        'name' => 'Coord Two',
        'email' => 'coordtwo@fieldtrack.local',
        'login_id' => '9123456712',
        'password' => Hash::make('6712'),
        'role' => UserRole::CenterCoordinator->value,
        'is_active' => true,
        'is_center_login' => false,
    ]);
    $existingManagerIds = $org['centerA']->centerManagers()->pluck('users.id')->all();

    Livewire::actingAs($org['admin'])
        ->test(CreateCenter::class)
        ->assertFormFieldVisible('coordinators')
        ->assertFormFieldHidden('centerManagers')
        ->assertFormFieldHidden('primary_center_manager_id')
        ->assertActionHasLabel(
            TestAction::make('createOption')->schemaComponent('coordinators'),
            '+ Create New Coordinator',
        )
        ->fillForm([
            'scheme_id' => $org['projectA']->id,
            'name' => 'Coordinator Center',
            'is_active' => true,
            'coordinators' => [$first->id, $second->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $center = Center::query()->where('name', 'Coordinator Center')->firstOrFail();

    expect($center->coordinators()->pluck('users.id')->all())->toContain($first->id, $second->id)
        ->and($center->coordinators)->toHaveCount(2)
        ->and($first->fresh()->coordinatedCenters()->pluck('centers.id')->all())->toContain($center->id)
        ->and($second->fresh()->coordinatedCenters()->pluck('centers.id')->all())->toContain($center->id)
        ->and($center->centerManagers)->toHaveCount(0)
        ->and($center->primary_center_manager_id)->toBeNull()
        ->and($center->login_user_id)->toBeNull()
        ->and($center->login_id)->toBe('coordinator_center')
        ->and($org['centerA']->fresh()->centerManagers()->pluck('users.id')->all())->toBe($existingManagerIds);

    $manager = User::query()->create([
        'name' => 'Later CM',
        'email' => 'latercm@fieldtrack.local',
        'login_id' => '9123456713',
        'password' => Hash::make('6713'),
        'role' => UserRole::CenterManager->value,
        'is_active' => true,
        'is_center_login' => false,
    ]);
    $managerPassword = $manager->getRawOriginal('password');

    Livewire::actingAs($org['admin'])
        ->test(EditCenter::class, ['record' => $center->getKey()])
        ->assertFormFieldVisible('centerManagers')
        ->assertFormFieldVisible('coordinators')
        ->assertDontSee('Center Login ID')
        ->assertDontSee('Reset Password')
        ->fillForm([
            'centerManagers' => [$manager->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($center->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$manager->id])
        ->and($center->fresh()->coordinators()->pluck('users.id')->all())->toContain($first->id, $second->id)
        ->and($center->fresh()->coordinators)->toHaveCount(2)
        ->and($center->fresh()->login_id)->toBe('coordinator_center')
        ->and($center->fresh()->login_user_id)->toBeNull()
        ->and(User::query()->where('is_center_login', true)->where('login_id', 'coordinator_center')->exists())->toBeFalse()
        ->and($manager->fresh()->getRawOriginal('password'))->toBe($managerPassword);
});

it('creates a coordinator from the center form and assigns them without a center login', function () {
    $org = seedOrg();

    Livewire::actingAs($org['projectHead'])
        ->test(CreateCenter::class)
        ->assertActionHidden(TestAction::make('createOption')->schemaComponent('coordinators'));

    Livewire::actingAs($org['admin'])
        ->test(CreateCenter::class)
        ->fillForm([
            'scheme_id' => $org['projectA']->id,
            'name' => 'New Coordinator Center',
            'is_active' => true,
        ])
        ->callAction(TestAction::make('createOption')->schemaComponent('coordinators'), [
            'name' => 'Modal Coordinator',
            'mobile' => '9123456788',
            'email' => null,
        ])
        ->assertHasNoFormErrors()
        ->call('create')
        ->assertHasNoFormErrors();

    $coordinator = User::query()->where('login_id', '9123456788')->firstOrFail();
    $center = Center::query()->where('name', 'New Coordinator Center')->firstOrFail();

    expect($coordinator->role)->toBe(UserRole::CenterCoordinator->value)
        ->and($coordinator->is_active)->toBeTrue()
        ->and($coordinator->is_center_login)->toBeFalse()
        ->and($coordinator->email)->toBe('9123456788@fieldtrack.local')
        ->and(Hash::check('6788', $coordinator->password))->toBeTrue()
        ->and($coordinator->getRawOriginal('password'))->not->toBe('6788')
        ->and($coordinator->must_change_password)->toBeTrue()
        ->and($center->coordinators()->pluck('users.id')->all())->toContain($coordinator->id)
        ->and($center->centerManagers)->toHaveCount(0)
        ->and($center->login_user_id)->toBeNull();
});

it('creates a center and two new coordinators in one transaction', function () {
    $org = seedOrg();
    $before = User::query()->count();

    $form = Livewire::actingAs($org['admin'])
        ->test(CreateCenter::class)
        ->fillForm([
            'scheme_id' => $org['projectA']->id,
            'name' => 'Atomic Center',
            'address' => '12 Atomic Road',
            'is_active' => true,
        ])
        ->callAction(TestAction::make('createOption')->schemaComponent('coordinators'), [
            'name' => 'Atomic One',
            'mobile' => '9123456711',
            'email' => null,
        ])
        ->assertHasNoFormErrors()
        ->callAction(TestAction::make('createOption')->schemaComponent('coordinators'), [
            'name' => 'Atomic Two',
            'mobile' => '9123456722',
            'email' => null,
        ])
        ->assertHasNoFormErrors();

    expect(User::query()->count())->toBe($before)
        ->and(Center::query()->where('name', 'Atomic Center')->exists())->toBeFalse()
        ->and($form->instance()->pendingCoordinators)->not->toHaveKey('password')
        ->and(json_encode($form->instance()->pendingCoordinators))->not->toContain('"password"');

    $form->call('create')->assertHasNoFormErrors();

    $center = Center::query()->where('name', 'Atomic Center')->firstOrFail();
    $first = User::query()->where('login_id', '9123456711')->firstOrFail();
    $second = User::query()->where('login_id', '9123456722')->firstOrFail();

    expect($center->coordinators()->pluck('users.id')->all())->toEqualCanonicalizing([$first->id, $second->id])
        ->and($center->login_user_id)->toBeNull()
        ->and(User::query()->where('is_center_login', true)->exists())->toBeFalse()
        ->and($first->must_change_password)->toBeTrue()
        ->and($second->must_change_password)->toBeTrue()
        ->and(Hash::check('6711', $first->password))->toBeTrue()
        ->and(Hash::check('6722', $second->password))->toBeTrue()
        ->and($first->getRawOriginal('password'))->not->toBe('6711')
        ->and($second->getRawOriginal('password'))->not->toBe('6722')
        ->and($form->html())->not->toContain('value="6711"')
        ->and($form->html())->not->toContain('value="6722"');
});

it('keeps the center form and creates nothing when the coordinator mobile is already used', function () {
    $org = seedOrg();
    User::query()->create([
        'name' => 'Existing Mobile',
        'email' => 'existingmobile@fieldtrack.local',
        'login_id' => '9123456733',
        'password' => Hash::make('6733'),
        'role' => UserRole::CenterCoordinator->value,
        'is_active' => true,
        'is_center_login' => false,
    ]);
    $before = User::query()->count();

    Livewire::actingAs($org['director'])
        ->test(CreateCenter::class)
        ->assertFormFieldVisible('coordinators')
        ->assertFormFieldHidden('centerManagers')
        ->assertDontSee('Center Login ID')
        ->assertDontSee('Reset Password')
        ->assertActionHasLabel(
            TestAction::make('createOption')->schemaComponent('coordinators'),
            '+ Create New Coordinator',
        )
        ->fillForm([
            'scheme_id' => $org['projectA']->id,
            'name' => 'Duplicate Mobile Center',
            'address' => 'Kept Address',
            'is_active' => true,
        ])
        ->callAction(TestAction::make('createOption')->schemaComponent('coordinators'), [
            'name' => 'Should Not Exist',
            'mobile' => '9123456733',
            'email' => null,
        ])
        ->assertHasActionErrors(['mobile'])
        ->assertSet('data.name', 'Duplicate Mobile Center')
        ->assertSet('data.address', 'Kept Address');

    expect(Center::query()->where('name', 'Duplicate Mobile Center')->exists())->toBeFalse()
        ->and(User::query()->where('name', 'Should Not Exist')->exists())->toBeFalse()
        ->and(User::query()->count())->toBe($before);
});

it('rolls back the center and the new coordinators when a mobile is taken before save', function () {
    $org = seedOrg();

    $form = Livewire::actingAs($org['admin'])
        ->test(CreateCenter::class)
        ->fillForm([
            'scheme_id' => $org['projectA']->id,
            'name' => 'Rollback Center',
            'address' => 'Still Here',
            'is_active' => true,
        ])
        ->callAction(TestAction::make('createOption')->schemaComponent('coordinators'), [
            'name' => 'Rollback One',
            'mobile' => '9123456744',
            'email' => null,
        ])
        ->callAction(TestAction::make('createOption')->schemaComponent('coordinators'), [
            'name' => 'Rollback Two',
            'mobile' => '9123456755',
            'email' => null,
        ]);

    User::query()->create([
        'name' => 'Race Winner',
        'email' => 'racewinner@fieldtrack.local',
        'login_id' => '9123456755',
        'password' => Hash::make('6755'),
        'role' => UserRole::Employee->value,
        'is_active' => true,
    ]);

    $form->call('create')
        ->assertHasErrors(['coordinators'])
        ->assertSet('data.name', 'Rollback Center')
        ->assertSet('data.address', 'Still Here');

    expect(Center::query()->where('name', 'Rollback Center')->exists())->toBeFalse()
        ->and(User::query()->where('login_id', '9123456744')->exists())->toBeFalse()
        ->and(User::query()->where('name', 'Rollback One')->exists())->toBeFalse()
        ->and(User::query()->where('login_id', '9123456755')->value('name'))->toBe('Race Winner');
});

it('adds and removes coordinators on edit without changing the manager', function () {
    $org = seedOrg();
    $managerIds = $org['centerA']->centerManagers()->pluck('users.id')->all();
    $kept = User::query()->create([
        'name' => 'Kept Coord',
        'email' => 'keptcoord@fieldtrack.local',
        'login_id' => '9123456766',
        'password' => Hash::make('6766'),
        'role' => UserRole::CenterCoordinator->value,
        'is_active' => true,
        'is_center_login' => false,
    ]);
    $org['centerA']->coordinators()->sync([$kept->id]);

    $edit = Livewire::actingAs($org['admin'])
        ->test(EditCenter::class, ['record' => $org['centerA']->getKey()])
        ->assertFormFieldVisible('coordinators')
        ->assertDontSee('Center Login ID')
        ->assertDontSee('Reset Password')
        ->callAction(TestAction::make('createOption')->schemaComponent('coordinators'), [
            'name' => 'Added Coord',
            'mobile' => '9123456777',
            'email' => null,
        ])
        ->assertHasNoFormErrors();

    expect(User::query()->where('login_id', '9123456777')->exists())->toBeFalse();

    $edit->call('save')->assertHasNoFormErrors();

    $added = User::query()->where('login_id', '9123456777')->firstOrFail();

    expect($org['centerA']->fresh()->coordinators()->pluck('users.id')->all())
        ->toEqualCanonicalizing([$kept->id, $added->id])
        ->and($org['centerA']->fresh()->centerManagers()->pluck('users.id')->all())->toBe($managerIds)
        ->and($added->must_change_password)->toBeTrue()
        ->and(Hash::check('6777', $added->password))->toBeTrue();

    Livewire::actingAs($org['admin'])
        ->test(EditCenter::class, ['record' => $org['centerA']->getKey()])
        ->fillForm([
            'coordinators' => [$added->id],
            'centerManagers' => $managerIds,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($org['centerA']->fresh()->coordinators()->pluck('users.id')->all())->toBe([$added->id])
        ->and($org['centerA']->fresh()->centerManagers()->pluck('users.id')->all())->toBe($managerIds);
});

it('requires a new coordinator to change the temporary password on the web login', function () {
    $org = seedOrg();

    Livewire::actingAs($org['admin'])
        ->test(CreateCenter::class)
        ->fillForm([
            'scheme_id' => $org['projectA']->id,
            'name' => 'Password Center',
            'is_active' => true,
        ])
        ->callAction(TestAction::make('createOption')->schemaComponent('coordinators'), [
            'name' => 'Must Change',
            'mobile' => '9123456781',
            'email' => null,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $coordinator = User::query()->where('login_id', '9123456781')->firstOrFail();
    expect($coordinator->must_change_password)->toBeTrue();

    auth()->logout();

    Livewire::test(Login::class)
        ->fillForm([
            'login_id' => '9123456781',
            'password' => '6781',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect(ChangePassword::getUrl());

    $this->actingAs($coordinator->fresh())
        ->get('/admin/centers')
        ->assertRedirect('/admin/change-password');

    $this->actingAs($coordinator->fresh())
        ->get('/admin/change-password')
        ->assertOk()
        ->assertSee('Change Password')
        ->assertDontSee('6781');

    Livewire::actingAs($coordinator->fresh())
        ->test(ChangePassword::class)
        ->fillForm([
            'currentPassword' => '6781',
            'password' => 'Coord@1234',
            'passwordConfirmation' => 'Coord@1234',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($coordinator->fresh()->must_change_password)->toBeFalse()
        ->and(Hash::check('Coord@1234', $coordinator->fresh()->password))->toBeTrue()
        ->and(Hash::check('6781', $coordinator->fresh()->password))->toBeFalse();

    $this->flushSession();

    $this->actingAs($coordinator->fresh())
        ->get('/admin/centers')
        ->assertOk();
});
