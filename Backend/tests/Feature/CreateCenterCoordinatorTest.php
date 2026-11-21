<?php

use App\Enums\UserRole;
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
        ->assertSee('Center Login ID')
        ->assertActionVisible('resetCenterPassword')
        ->fillForm([
            'centerManagers' => [$manager->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($center->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$manager->id])
        ->and($center->fresh()->coordinators()->pluck('users.id')->all())->toContain($first->id, $second->id)
        ->and($center->fresh()->coordinators)->toHaveCount(2)
        ->and($center->fresh()->login_id)->toBe('coordinator_center')
        ->and($center->fresh()->login_user_id)->not->toBeNull()
        ->and(Hash::check('6713', $center->fresh()->loginUser->password))->toBeTrue()
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
        ->and($center->coordinators()->pluck('users.id')->all())->toContain($coordinator->id)
        ->and($center->centerManagers)->toHaveCount(0)
        ->and($center->login_user_id)->toBeNull();
});
