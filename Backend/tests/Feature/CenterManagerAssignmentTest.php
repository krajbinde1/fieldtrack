<?php

use App\Enums\UserRole;
use App\Filament\Resources\Centers\Pages\CreateCenter;
use App\Filament\Resources\Centers\Pages\EditCenter;
use App\Filament\Resources\Centers\Pages\ListCenters;
use App\Filament\Resources\OrgUsers\Pages\CreateOrgUser;
use App\Filament\Resources\OrgUsers\Pages\EditOrgUser;
use App\Models\Center;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('assigns center managers from the user form and reflects them on the center list', function () {
    $org = seedOrg();

    Livewire::actingAs($org['admin'])
        ->test(CreateCenter::class)
        ->assertFormFieldExists('centerManagers')
        ->assertActionHasLabel(
            TestAction::make('createOption')->schemaComponent('centerManagers'),
            '+ Create New Center Manager',
        )
        ->fillForm([
            'scheme_id' => $org['projectA']->id,
            'name' => 'Unassigned Center',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $center = Center::query()->where('name', 'Unassigned Center')->firstOrFail();
    expect($center->centerManagers)->toHaveCount(0);

    Livewire::actingAs($org['admin'])
        ->test(CreateOrgUser::class)
        ->fillForm([
            'role' => UserRole::CenterManager->value,
            'name' => 'Assigned CM',
            'login_id' => 'assignedcm',
            'email' => 'assignedcm@fieldtrack.local',
            'password' => 'CenterMgr@123',
            'managedCenters' => [$center->id],
            'is_active' => true,
            'must_change_password' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $manager = User::query()->where('login_id', 'assignedcm')->firstOrFail();
    expect($manager->role)->toBe(UserRole::CenterManager->value)
        ->and($manager->managedCenters()->pluck('centers.id')->all())->toContain($center->id)
        ->and($center->fresh()->centerManagers->pluck('id')->all())->toContain($manager->id)
        ->and($org['centerA']->fresh()->centerManagers->pluck('id')->all())->toContain($org['centerManager']->id);

    Livewire::actingAs($org['admin'])
        ->test(EditCenter::class, ['record' => $center->getKey()])
        ->assertFormFieldExists('centerManagers');

    Livewire::actingAs($org['admin'])
        ->test(ListCenters::class)
        ->assertSee('Assigned CM')
        ->assertSee('CM');
});

it('assigns selected center managers from the center form and reflects them on users', function () {
    $org = seedOrg();
    $secondManager = User::query()->create([
        'name' => 'Second CM',
        'email' => 'secondcm@fieldtrack.local',
        'login_id' => '9876500001',
        'password' => Hash::make('0001'),
        'role' => UserRole::CenterManager->value,
        'is_active' => true,
        'must_change_password' => true,
    ]);

    Livewire::actingAs($org['admin'])
        ->test(CreateCenter::class)
        ->fillForm([
            'scheme_id' => $org['projectA']->id,
            'name' => 'Managed From Center',
            'address' => '1 Center Road',
            'is_active' => true,
            'centerManagers' => [$org['centerManager']->id, $secondManager->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $center = Center::query()->where('name', 'Managed From Center')->firstOrFail();
    expect($center->centerManagers()->pluck('users.id')->sort()->values()->all())
        ->toBe(collect([$org['centerManager']->id, $secondManager->id])->sort()->values()->all())
        ->and($org['centerManager']->fresh()->managedCenters()->pluck('centers.id')->all())
        ->toContain($org['centerA']->id, $center->id)
        ->and($secondManager->fresh()->managedCenters()->pluck('centers.id')->all())
        ->toContain($center->id)
        ->and($org['centerA']->fresh()->centerManagers->pluck('id')->all())
        ->toContain($org['centerManager']->id);

    Livewire::actingAs($org['admin'])
        ->test(EditOrgUser::class, ['record' => $org['centerManager']->getKey()])
        ->assertSee($center->name);
});

it('creates a center manager from the center form modal, auto-selects them, and assigns on save', function () {
    $org = seedOrg();

    Livewire::actingAs($org['admin'])
        ->test(CreateCenter::class)
        ->fillForm([
            'scheme_id' => $org['projectA']->id,
            'name' => 'Modal Assigned Center',
            'is_active' => true,
        ])
        ->callAction(TestAction::make('createOption')->schemaComponent('centerManagers'), [
            'name' => 'Modal CM',
            'mobile' => '9123456790',
            'email' => null,
        ])
        ->assertHasNoFormErrors()
        ->call('create')
        ->assertHasNoFormErrors();

    $manager = User::query()->where('login_id', '9123456790')->first();
    expect($manager)->not->toBeNull()
        ->and($manager->name)->toBe('Modal CM')
        ->and($manager->role)->toBe(UserRole::CenterManager->value)
        ->and($manager->email)->toBe('9123456790@fieldtrack.local')
        ->and(Hash::check('6790', $manager->password))->toBeTrue()
        ->and($manager->getRawOriginal('password'))->not->toBe('6790');

    $center = Center::query()->where('name', 'Modal Assigned Center')->firstOrFail();
    expect($center->centerManagers->pluck('id')->all())->toContain($manager->id)
        ->and($manager->managedCenters()->pluck('centers.id')->all())->toContain($center->id)
        ->and($org['centerA']->fresh()->centerManagers->pluck('id')->all())->toContain($org['centerManager']->id);

    $this->postJson('/api/login', [
        'login_id' => '9123456790',
        'password' => '6790',
        'device_id' => 'device-cm-modal',
    ])->assertOk()->assertJsonPath('user.login_id', '9123456790');
});

it('lets a project head create a center manager from the center form', function () {
    $org = seedOrg();

    Livewire::actingAs($org['projectHead'])
        ->test(CreateCenter::class)
        ->fillForm([
            'scheme_id' => $org['projectA']->id,
            'name' => 'PH Modal Center',
            'is_active' => true,
        ])
        ->callAction(TestAction::make('createOption')->schemaComponent('centerManagers'), [
            'name' => 'PH Created CM',
            'mobile' => '9123456791',
            'email' => 'phcreatedcm@fieldtrack.local',
        ])
        ->assertHasNoFormErrors()
        ->call('create')
        ->assertHasNoFormErrors();

    $manager = User::query()->where('login_id', '9123456791')->firstOrFail();
    $center = Center::query()->where('name', 'PH Modal Center')->firstOrFail();

    expect($manager->email)->toBe('phcreatedcm@fieldtrack.local')
        ->and(Hash::check('6791', $manager->password))->toBeTrue()
        ->and($center->centerManagers->pluck('id')->all())->toContain($manager->id)
        ->and($org['projectHead']->fresh()->headedCenters()->pluck('centers.id')->all())->toContain($center->id);
});

it('adds and removes center managers on edit without wiping other centers', function () {
    $org = seedOrg();
    $other = User::query()->create([
        'name' => 'Other CM',
        'email' => 'othercm@fieldtrack.local',
        'login_id' => '9876500002',
        'password' => Hash::make('0002'),
        'role' => UserRole::CenterManager->value,
        'is_active' => true,
    ]);

    Livewire::actingAs($org['admin'])
        ->test(EditCenter::class, ['record' => $org['centerA']->getKey()])
        ->fillForm([
            'centerManagers' => [$org['centerManager']->id, $other->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($org['centerA']->fresh()->centerManagers->pluck('id')->sort()->values()->all())
        ->toBe(collect([$org['centerManager']->id, $other->id])->sort()->values()->all())
        ->and($org['centerB']->fresh()->centerManagers)->toHaveCount(0);

    Livewire::actingAs($org['admin'])
        ->test(EditCenter::class, ['record' => $org['centerA']->getKey()])
        ->fillForm([
            'centerManagers' => [$other->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($org['centerA']->fresh()->centerManagers->pluck('id')->all())->toBe([$other->id])
        ->and($org['centerManager']->fresh()->managedCenters()->pluck('centers.id')->all())->not->toContain($org['centerA']->id)
        ->and($other->fresh()->managedCenters()->pluck('centers.id')->all())->toContain($org['centerA']->id);
});

it('rejects a duplicate mobile number when creating a center manager from the center form', function () {
    $org = seedOrg();
    $org['centerManager']->update(['login_id' => '9123456799']);

    Livewire::actingAs($org['admin'])
        ->test(CreateCenter::class)
        ->callAction(TestAction::make('createOption')->schemaComponent('centerManagers'), [
            'name' => 'Duplicate CM',
            'mobile' => '9123456799',
            'email' => null,
        ])
        ->assertHasActionErrors(['mobile']);

    expect(User::query()->where('name', 'Duplicate CM')->exists())->toBeFalse();
});
