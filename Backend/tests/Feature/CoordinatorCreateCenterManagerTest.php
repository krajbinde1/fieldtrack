<?php

use App\Enums\UserRole;
use App\Filament\Resources\Centers\Pages\ListCenters;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\OrgUsers\OrgUserResource;
use App\Filament\Resources\OrgUsers\Pages\EditOrgUser;
use App\Models\Center;
use App\Models\User;
use App\Services\CenterManagerAccountService;
use App\Services\OrganizationAccessService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function createManagerCoordinator(string $name, string $loginId): User
{
    return User::query()->create([
        'name' => $name,
        'email' => $loginId.'@fieldtrack.local',
        'login_id' => $loginId,
        'password' => Hash::make('1234'),
        'role' => UserRole::CenterCoordinator->value,
        'is_active' => true,
        'is_center_login' => false,
        'must_change_password' => false,
    ]);
}

it('shows create center manager to coordinators and creates one for their centers', function () {
    $org = seedOrg();
    $coordinator = createManagerCoordinator('Creator Coord', '9123456900');
    $coordinator->coordinatedCenters()->attach($org['centerA']->id);
    $org['centerA']->forceFill(['primary_center_manager_id' => $org['centerManager']->id])->save();
    $beforeUsers = User::query()->pluck('id')->all();
    $directorCenters = $org['director']->directedCenters()->pluck('centers.id')->all();
    $headCenters = $org['projectHead']->headedCenters()->pluck('centers.id')->all();
    $coordinatorCenters = $coordinator->coordinatedCenters()->pluck('centers.id')->all();
    $originalPassword = $org['centerManager']->getRawOriginal('password');

    Livewire::actingAs($coordinator)
        ->test(ListEmployees::class)
        ->assertActionVisible('createCenterManager')
        ->assertSee('Create Center Manager');

    Livewire::actingAs($coordinator)
        ->test(ListCenters::class)
        ->assertActionVisible('createCenterManager');

    Livewire::actingAs($org['admin'])
        ->test(ListCenters::class)
        ->assertActionHidden('createCenterManager');

    Livewire::actingAs($org['centerManager'])
        ->test(ListEmployees::class)
        ->assertActionHidden('createCenterManager');

    Livewire::actingAs($coordinator)
        ->test(ListEmployees::class)
        ->callAction('createCenterManager', [
            'name' => 'Created Manager',
            'mobile' => '9123456901',
            'email' => null,
            'center_ids' => [(string) $org['centerA']->id],
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Center Manager created. Login ID: 9123456901. Temporary password: 6901.');

    $manager = User::query()->where('login_id', '9123456901')->firstOrFail();

    expect($manager->role)->toBe(UserRole::CenterManager->value)
        ->and($manager->is_center_login)->toBeFalse()
        ->and($manager->is_active)->toBeTrue()
        ->and($manager->must_change_password)->toBeTrue()
        ->and(Hash::check('6901', $manager->password))->toBeTrue()
        ->and($manager->getRawOriginal('password'))->not->toBe('6901')
        ->and($org['centerA']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$manager->id])
        ->and($org['centerA']->fresh()->primary_center_manager_id)->toBe($manager->id)
        ->and($org['centerB']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([])
        ->and($org['centerB']->fresh()->primary_center_manager_id)->toBeNull()
        ->and($org['centerManager']->fresh()->exists)->toBeTrue()
        ->and($org['centerManager']->fresh()->is_active)->toBeTrue()
        ->and($org['centerManager']->fresh()->role)->toBe(UserRole::CenterManager->value)
        ->and($org['centerManager']->fresh()->managedCenters()->pluck('centers.id')->all())->toBe([])
        ->and($org['centerManager']->fresh()->getRawOriginal('password'))->toBe($originalPassword)
        ->and($coordinator->fresh()->role)->toBe(UserRole::CenterCoordinator->value)
        ->and($coordinator->fresh()->coordinatedCenters()->pluck('centers.id')->all())->toBe($coordinatorCenters)
        ->and($org['director']->fresh()->directedCenters()->pluck('centers.id')->all())->toBe($directorCenters)
        ->and($org['projectHead']->fresh()->headedCenters()->pluck('centers.id')->all())->toBe($headCenters)
        ->and($org['admin']->fresh()->role)->toBe(UserRole::Admin->value)
        ->and($org['empA']->fresh()->center_id)->toBe($org['centerA']->id)
        ->and(User::query()->whereIn('id', $beforeUsers)->count())->toBe(count($beforeUsers));
});

it('assigns one new center manager to several of the coordinator centers', function () {
    $org = seedOrg();
    $coordinator = createManagerCoordinator('Multi Creator', '9123456910');
    $coordinator->coordinatedCenters()->attach([$org['centerA']->id, $org['centerB']->id]);
    $org['centerManager']->managedCenters()->attach($org['centerB']->id);
    $org['centerA']->forceFill(['primary_center_manager_id' => $org['centerManager']->id])->save();
    $org['centerB']->forceFill(['primary_center_manager_id' => $org['centerManager']->id])->save();

    Livewire::actingAs($coordinator)
        ->test(ListCenters::class)
        ->callAction('createCenterManager', [
            'name' => 'Shared Created Manager',
            'mobile' => '9123456911',
            'center_ids' => [(string) $org['centerA']->id, (string) $org['centerB']->id],
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Center Manager created. Login ID: 9123456911. Temporary password: 6911.');

    $manager = User::query()->where('login_id', '9123456911')->firstOrFail();

    expect($org['centerA']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$manager->id])
        ->and($org['centerB']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$manager->id])
        ->and($org['centerA']->fresh()->primary_center_manager_id)->toBe($manager->id)
        ->and($org['centerB']->fresh()->primary_center_manager_id)->toBe($manager->id)
        ->and($manager->managedCenters()->pluck('centers.id')->all())->toEqualCanonicalizing([$org['centerA']->id, $org['centerB']->id])
        ->and($org['centerManager']->fresh()->managedCenters()->pluck('centers.id')->all())->toBe([])
        ->and($org['centerManager']->fresh()->is_active)->toBeTrue()
        ->and(User::query()->where('role', UserRole::CenterManager->value)->where('is_center_login', false)->count())->toBe(2);
});

it('replaces the manager on one center and leaves the old manager other centers alone', function () {
    $org = seedOrg();
    $coordinator = createManagerCoordinator('Replace Coord', '9123456920');
    $coordinator->coordinatedCenters()->attach([$org['centerA']->id, $org['centerB']->id]);
    $org['centerManager']->managedCenters()->attach($org['centerB']->id);
    $org['centerA']->forceFill(['primary_center_manager_id' => $org['centerManager']->id])->save();
    $org['centerB']->forceFill(['primary_center_manager_id' => $org['centerManager']->id])->save();

    Livewire::actingAs($coordinator)
        ->test(ListEmployees::class)
        ->callAction('createCenterManager', [
            'name' => 'Replacement Only A',
            'mobile' => '9123456921',
            'center_ids' => [(string) $org['centerA']->id],
        ])
        ->assertHasNoActionErrors();

    $manager = User::query()->where('login_id', '9123456921')->firstOrFail();

    expect($org['centerA']->fresh()->centerManagers()->count())->toBe(1)
        ->and($org['centerA']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$manager->id])
        ->and($org['centerA']->fresh()->primary_center_manager_id)->toBe($manager->id)
        ->and($org['centerB']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$org['centerManager']->id])
        ->and($org['centerB']->fresh()->primary_center_manager_id)->toBe($org['centerManager']->id)
        ->and($org['centerManager']->fresh()->managedCenters()->pluck('centers.id')->all())->toBe([$org['centerB']->id])
        ->and($org['centerManager']->fresh()->is_active)->toBeTrue()
        ->and($manager->managedCenters()->pluck('centers.id')->all())->toBe([$org['centerA']->id]);
});

it('blocks a coordinator from assigning or editing a manager outside their centers', function () {
    $org = seedOrg();
    $coordinator = createManagerCoordinator('Scoped Coord', '9123456930');
    $coordinator->coordinatedCenters()->attach($org['centerA']->id);
    $outside = User::query()->create([
        'name' => 'Outside Manager',
        'email' => 'outside-manager@fieldtrack.local',
        'login_id' => '9123456931',
        'password' => Hash::make('6931'),
        'role' => UserRole::CenterManager->value,
        'is_active' => true,
        'is_center_login' => false,
        'must_change_password' => false,
    ]);
    $outside->managedCenters()->attach($org['centerB']->id);
    $org['centerB']->forceFill(['primary_center_manager_id' => $outside->id])->save();
    $otherCoordinator = createManagerCoordinator('Other Coord', '9123456932');
    $otherCoordinator->coordinatedCenters()->attach($org['centerB']->id);
    $password = $outside->getRawOriginal('password');

    expect(fn () => app(CenterManagerAccountService::class)->provisionForCoordinator($coordinator, [
        'name' => 'Should Not Exist',
        'mobile' => '9123456933',
        'center_ids' => [$org['centerB']->id],
    ]))->toThrow(ValidationException::class);

    $blocked = false;
    try {
        Livewire::actingAs($coordinator)
            ->test(ListEmployees::class)
            ->callAction('createCenterManager', [
                'name' => 'Should Not Exist',
                'mobile' => '9123456933',
                'center_ids' => [(string) $org['centerB']->id],
            ]);
    } catch (Throwable) {
        $blocked = true;
    }

    expect($blocked || User::query()->where('login_id', '9123456933')->doesntExist())->toBeTrue()
        ->and(User::query()->where('name', 'Should Not Exist')->exists())->toBeFalse()
        ->and($org['centerB']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$outside->id])
        ->and($outside->fresh()->getRawOriginal('password'))->toBe($password);

    $this->actingAs($coordinator);
    $this->get(OrgUserResource::getUrl('edit', ['record' => $outside]))->assertNotFound();
    $this->get(OrgUserResource::getUrl('edit', ['record' => $org['admin']]))->assertNotFound();
    $this->get(OrgUserResource::getUrl('edit', ['record' => $org['director']]))->assertNotFound();
    $this->get(OrgUserResource::getUrl('edit', ['record' => $otherCoordinator]))->assertNotFound();
    $this->get(OrgUserResource::getUrl('edit', ['record' => $coordinator]))->assertNotFound();

    expect(app(OrganizationAccessService::class)->canChangeOrgUserRole($coordinator, $outside))->toBeFalse()
        ->and(app(OrganizationAccessService::class)->canManageOrgUser($coordinator, $outside))->toBeFalse()
        ->and(app(OrganizationAccessService::class)->canManageOrgUser($coordinator, $org['admin']))->toBeFalse()
        ->and(app(OrganizationAccessService::class)->canManageOrgUser($coordinator, $coordinator))->toBeFalse();

    expect($coordinator->fresh()->role)->toBe(UserRole::CenterCoordinator->value)
        ->and($coordinator->fresh()->coordinatedCenters()->pluck('centers.id')->all())->toBe([$org['centerA']->id])
        ->and($outside->fresh()->managedCenters()->pluck('centers.id')->all())->toBe([$org['centerB']->id]);
});

it('lets a coordinator assign an existing manager to another of their centers and edit only in-scope managers', function () {
    $org = seedOrg();
    $coordinator = createManagerCoordinator('Assign Coord', '9123456940');
    $coordinator->coordinatedCenters()->attach([$org['centerA']->id, $org['centerB']->id]);
    $keptPassword = $org['centerManager']->getRawOriginal('password');
    $outsideOnly = User::query()->create([
        'name' => 'Foreign Manager',
        'email' => 'foreign-manager@fieldtrack.local',
        'login_id' => '9123456941',
        'password' => Hash::make('6941'),
        'role' => UserRole::CenterManager->value,
        'is_active' => true,
        'is_center_login' => false,
    ]);
    $third = Center::query()->create([
        'scheme_id' => $org['projectB']->id,
        'name' => 'Center C',
        'code' => 'CC',
        'is_active' => true,
    ]);
    $outsideOnly->managedCenters()->attach($third->id);
    $third->forceFill(['primary_center_manager_id' => $outsideOnly->id])->save();

    Livewire::actingAs($coordinator)
        ->test(ListEmployees::class)
        ->callAction('createCenterManager', [
            'existing_manager_id' => (string) $org['centerManager']->id,
            'center_ids' => [(string) $org['centerB']->id],
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Existing Center Manager assigned.');

    expect($org['centerA']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$org['centerManager']->id])
        ->and($org['centerB']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$org['centerManager']->id])
        ->and($org['centerB']->fresh()->primary_center_manager_id)->toBe($org['centerManager']->id)
        ->and($org['centerManager']->fresh()->managedCenters()->pluck('centers.id')->all())
        ->toEqualCanonicalizing([$org['centerA']->id, $org['centerB']->id])
        ->and($org['centerManager']->fresh()->getRawOriginal('password'))->toBe($keptPassword)
        ->and($org['centerManager']->fresh()->must_change_password)->toBeFalse()
        ->and($third->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$outsideOnly->id]);

    Livewire::actingAs($coordinator)
        ->test(ListEmployees::class)
        ->callAction('createCenterManager', [
            'existing_manager_id' => (string) $outsideOnly->id,
            'center_ids' => [(string) $org['centerA']->id],
        ])
        ->assertHasNoActionErrors();

    expect($outsideOnly->fresh()->managedCenters()->pluck('centers.id')->all())
        ->toEqualCanonicalizing([$third->id, $org['centerA']->id])
        ->and($org['centerA']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$outsideOnly->id])
        ->and($org['centerB']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$org['centerManager']->id])
        ->and($third->fresh()->primary_center_manager_id)->toBe($outsideOnly->id)
        ->and($org['centerManager']->fresh()->managedCenters()->pluck('centers.id')->all())->toBe([$org['centerB']->id]);

    Livewire::actingAs($coordinator)
        ->test(EditOrgUser::class, ['record' => $outsideOnly->getKey()])
        ->assertForbidden();

    Livewire::actingAs($coordinator)
        ->test(EditOrgUser::class, ['record' => $org['centerManager']->getKey()])
        ->fillForm([
            'name' => 'CM Renamed',
            'managedCenters' => [$org['centerB']->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($org['centerManager']->fresh()->name)->toBe('CM Renamed')
        ->and($org['centerManager']->fresh()->role)->toBe(UserRole::CenterManager->value)
        ->and($org['centerManager']->fresh()->managedCenters()->pluck('centers.id')->all())->toBe([$org['centerB']->id])
        ->and($coordinator->fresh()->role)->toBe(UserRole::CenterCoordinator->value)
        ->and($coordinator->fresh()->coordinatedCenters()->pluck('centers.id')->all())
        ->toEqualCanonicalizing([$org['centerA']->id, $org['centerB']->id])
        ->and($org['admin']->fresh()->role)->toBe(UserRole::Admin->value);
});
