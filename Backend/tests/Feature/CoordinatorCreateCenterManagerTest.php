<?php

use App\Enums\CenterStaffRole;
use App\Enums\UserRole;
use App\Filament\Resources\Centers\Pages\EditCenter;
use App\Filament\Resources\Centers\Pages\ListCenters;
use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\OrgUsers\OrgUserResource;
use App\Filament\Resources\OrgUsers\Pages\CreateOrgUser;
use App\Filament\Resources\OrgUsers\Pages\EditOrgUser;
use App\Models\Center;
use App\Models\Employee;
use App\Models\User;
use App\Services\CenterManagerAccountService;
use App\Services\CenterStaffCredentialService;
use App\Services\OrganizationAccessService;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Select;
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

function coordinatorLoginRoleOptions(): array
{
    return [
        UserRole::CenterManager->value => 'Center Manager',
        CenterStaffRole::Trainer->value => 'Trainer',
        CenterStaffRole::Mobilizer->value => 'Mobilizer',
        CenterStaffRole::FoodHostelSupervisor->value => 'Food & Hostel Supervisor',
    ];
}

it('lists center manager on the coordinator new user form and hides the separate button', function () {
    $org = seedOrg();
    $coordinator = createManagerCoordinator('Creator Coord', '9123456900');
    $coordinator->coordinatedCenters()->attach($org['centerA']->id);

    Livewire::actingAs($coordinator)
        ->test(ListEmployees::class)
        ->assertActionVisible('create')
        ->assertSee('New User')
        ->assertActionDoesNotExist('createCenterManager')
        ->assertDontSee('Create Center Manager');

    Livewire::actingAs($coordinator)
        ->test(ListCenters::class)
        ->assertActionDoesNotExist('createCenterManager');

    Livewire::actingAs($org['admin'])
        ->test(ListCenters::class)
        ->assertActionDoesNotExist('createCenterManager');

    Livewire::actingAs($org['director'])
        ->test(ListCenters::class)
        ->assertActionDoesNotExist('createCenterManager');

    Livewire::actingAs($org['centerManager'])
        ->test(ListEmployees::class)
        ->assertActionDoesNotExist('createCenterManager');

    Livewire::actingAs($coordinator)
        ->test(CreateEmployee::class)
        ->assertFormFieldExists('full_name')
        ->assertFormFieldExists('mobile')
        ->assertFormFieldExists('email')
        ->assertFormFieldExists('center_id')
        ->assertFormFieldExists('status')
        ->assertFormFieldExists('staff_role', function (Select $field): bool {
            expect($field->getOptions())->toBe(coordinatorLoginRoleOptions())
                ->and(array_key_exists(UserRole::Admin->value, $field->getOptions()))->toBeFalse()
                ->and(array_key_exists(UserRole::Director->value, $field->getOptions()))->toBeFalse()
                ->and(array_key_exists(UserRole::CenterCoordinator->value, $field->getOptions()))->toBeFalse();

            return true;
        });

    Livewire::actingAs($org['centerManager'])
        ->test(CreateEmployee::class)
        ->assertFormFieldExists('staff_role', function (Select $field): bool {
            expect($field->getOptions())->toBe(CenterStaffRole::options())
                ->and(array_key_exists(UserRole::CenterManager->value, $field->getOptions()))->toBeFalse();

            return true;
        });
});

it('creates a center manager from the new user form for an assigned center', function () {
    $org = seedOrg();
    $coordinator = createManagerCoordinator('Creator Coord', '9123456900');
    $coordinator->coordinatedCenters()->attach($org['centerA']->id);
    $org['centerA']->centerManagers()->detach();
    $org['centerA']->forceFill(['primary_center_manager_id' => null])->save();
    $beforeUsers = User::query()->pluck('id')->all();
    $directorCenters = $org['director']->directedCenters()->pluck('centers.id')->all();
    $headCenters = $org['projectHead']->headedCenters()->pluck('centers.id')->all();
    $coordinatorCenters = $coordinator->coordinatedCenters()->pluck('centers.id')->all();
    $originalPassword = $org['centerManager']->getRawOriginal('password');

    Livewire::actingAs($coordinator)
        ->test(CreateEmployee::class)
        ->fillForm([
            'center_id' => $org['centerA']->id,
            'full_name' => 'Created Manager',
            'mobile' => '9123456901',
            'email' => null,
            'staff_role' => UserRole::CenterManager->value,
            'status' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('Center Manager created. Login ID: 9123456901. Temporary password: 6901.');

    $manager = User::query()->where('login_id', '9123456901')->firstOrFail();

    expect($manager->role)->toBe(UserRole::CenterManager->value)
        ->and($manager->is_center_login)->toBeFalse()
        ->and($manager->is_active)->toBeTrue()
        ->and($manager->must_change_password)->toBeTrue()
        ->and(Hash::check('6901', $manager->password))->toBeTrue()
        ->and($manager->getRawOriginal('password'))->not->toBe('6901')
        ->and(Employee::query()->where('mobile', '9123456901')->exists())->toBeFalse()
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

    $this->postJson('/api/login', [
        'login_id' => '9123456901',
        'password' => '6901',
        'device_id' => 'device-new-manager',
    ])->assertOk()
        ->assertJsonPath('user.login_id', '9123456901')
        ->assertJsonPath('user.role', UserRole::CenterManager->value)
        ->assertJsonPath('user.must_change_password', true);
});

it('rejects a second center manager and keeps the existing replacement workflow', function () {
    $org = seedOrg();
    $coordinator = createManagerCoordinator('Replace Coord', '9123456920');
    $coordinator->coordinatedCenters()->attach([$org['centerA']->id, $org['centerB']->id]);
    $org['centerManager']->managedCenters()->attach($org['centerB']->id);
    $org['centerA']->forceFill(['primary_center_manager_id' => $org['centerManager']->id])->save();
    $org['centerB']->forceFill(['primary_center_manager_id' => $org['centerManager']->id])->save();
    $before = User::query()->count();

    Livewire::actingAs($coordinator)
        ->test(CreateEmployee::class)
        ->fillForm([
            'center_id' => $org['centerA']->id,
            'full_name' => 'Duplicate Manager',
            'mobile' => '9123456921',
            'staff_role' => UserRole::CenterManager->value,
            'status' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['center_id' => CenterManagerAccountService::DUPLICATE_CENTER_MANAGER]);

    expect(User::query()->where('login_id', '9123456921')->exists())->toBeFalse()
        ->and(User::query()->where('name', 'Duplicate Manager')->exists())->toBeFalse()
        ->and(User::query()->count())->toBe($before)
        ->and($org['centerA']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$org['centerManager']->id])
        ->and($org['centerB']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$org['centerManager']->id]);

    $result = app(CenterManagerAccountService::class)->provisionForCoordinator($coordinator, [
        'name' => 'Replacement Only A',
        'mobile' => '9123456921',
        'center_ids' => [$org['centerA']->id],
    ]);

    $manager = $result['user'];

    expect($result['created'])->toBeTrue()
        ->and($manager->login_id)->toBe('9123456921')
        ->and($manager->must_change_password)->toBeTrue()
        ->and(Hash::check('6921', $manager->password))->toBeTrue()
        ->and($org['centerA']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$manager->id])
        ->and($org['centerA']->fresh()->primary_center_manager_id)->toBe($manager->id)
        ->and($org['centerB']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$org['centerManager']->id])
        ->and($org['centerB']->fresh()->primary_center_manager_id)->toBe($org['centerManager']->id)
        ->and($org['centerManager']->fresh()->managedCenters()->pluck('centers.id')->all())->toBe([$org['centerB']->id])
        ->and($org['centerManager']->fresh()->is_active)->toBeTrue()
        ->and($org['centerManager']->fresh()->exists)->toBeTrue();
});

it('blocks a coordinator from another center and from privileged login roles', function () {
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
    $org['centerA']->centerManagers()->detach();
    $org['centerA']->forceFill(['primary_center_manager_id' => null])->save();

    expect(fn () => app(CenterManagerAccountService::class)->createOnlyForCoordinator($coordinator, [
        'name' => 'Should Not Exist',
        'mobile' => '9123456933',
        'center_id' => $org['centerB']->id,
    ]))->toThrow(ValidationException::class);

    Livewire::actingAs($coordinator)
        ->test(CreateEmployee::class)
        ->fillForm([
            'center_id' => $org['centerB']->id,
            'full_name' => 'Should Not Exist',
            'mobile' => '9123456933',
            'staff_role' => UserRole::CenterManager->value,
            'status' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['center_id']);

    expect(User::query()->where('login_id', '9123456933')->exists())->toBeFalse()
        ->and(User::query()->where('name', 'Should Not Exist')->exists())->toBeFalse()
        ->and($org['centerB']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$outside->id])
        ->and($outside->fresh()->getRawOriginal('password'))->toBe($password)
        ->and($org['centerA']->fresh()->centerManagers()->count())->toBe(0);

    foreach ([UserRole::Admin->value, UserRole::Director->value, UserRole::CenterCoordinator->value] as $role) {
        expect(fn () => app(CenterStaffCredentialService::class)->placeEmployee([
            'center_id' => $org['centerA']->id,
            'full_name' => 'Privileged '.$role,
            'mobile' => '9123456934',
            'staff_role' => $role,
            'status' => true,
        ]))->toThrow(ValidationException::class);

        Livewire::actingAs($coordinator)
            ->test(CreateEmployee::class)
            ->fillForm([
                'center_id' => $org['centerA']->id,
                'full_name' => 'Privileged '.$role,
                'mobile' => '9123456934',
                'staff_role' => $role,
                'status' => true,
            ])
            ->call('create');

        expect(User::query()->where('name', 'Privileged '.$role)->exists())->toBeFalse()
            ->and(Employee::query()->where('full_name', 'Privileged '.$role)->exists())->toBeFalse();
    }

    $this->actingAs($coordinator);
    $this->get(OrgUserResource::getUrl('edit', ['record' => $outside]))->assertNotFound();
    $this->get(OrgUserResource::getUrl('edit', ['record' => $org['admin']]))->assertNotFound();
    $this->get(OrgUserResource::getUrl('edit', ['record' => $org['director']]))->assertNotFound();
    $this->get(OrgUserResource::getUrl('edit', ['record' => $otherCoordinator]))->assertNotFound();
    $this->get(OrgUserResource::getUrl('edit', ['record' => $coordinator]))->assertNotFound();

    expect(app(OrganizationAccessService::class)->canChangeOrgUserRole($coordinator, $outside))->toBeFalse()
        ->and(app(OrganizationAccessService::class)->canManageOrgUser($coordinator, $outside))->toBeFalse()
        ->and(app(OrganizationAccessService::class)->canManageOrgUser($coordinator, $org['admin']))->toBeFalse()
        ->and(app(OrganizationAccessService::class)->canManageOrgUser($coordinator, $coordinator))->toBeFalse()
        ->and($coordinator->fresh()->role)->toBe(UserRole::CenterCoordinator->value)
        ->and($coordinator->fresh()->coordinatedCenters()->pluck('centers.id')->all())->toBe([$org['centerA']->id])
        ->and($outside->fresh()->managedCenters()->pluck('centers.id')->all())->toBe([$org['centerB']->id]);
});

it('lets admin and director keep their existing center manager creation paths', function () {
    $org = seedOrg();
    $access = app(OrganizationAccessService::class);

    expect($access->creatableOrgUserRoles($org['admin']))->toContain(UserRole::CenterManager->value)
        ->and($access->creatableOrgUserRoles($org['director']))->toContain(UserRole::CenterManager->value);

    Livewire::actingAs($org['admin'])
        ->test(EditCenter::class, ['record' => $org['centerA']->getKey()])
        ->assertActionHasLabel(
            TestAction::make('createOption')->schemaComponent('centerManagers'),
            '+ Create New Center Manager',
        );

    Livewire::actingAs($org['director'])
        ->test(EditCenter::class, ['record' => $org['centerA']->getKey()])
        ->assertActionHasLabel(
            TestAction::make('createOption')->schemaComponent('centerManagers'),
            '+ Create New Center Manager',
        );

    Livewire::actingAs($org['admin'])
        ->test(CreateOrgUser::class)
        ->fillForm([
            'role' => UserRole::CenterManager->value,
            'name' => 'Admin Created Manager',
            'login_id' => 'admincm01',
            'email' => 'admincm01@fieldtrack.local',
            'password' => 'CenterMgr@123',
            'managedCenters' => [$org['centerB']->id],
            'is_active' => true,
            'must_change_password' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $adminManager = User::query()->where('login_id', 'admincm01')->firstOrFail();
    expect($adminManager->role)->toBe(UserRole::CenterManager->value)
        ->and($adminManager->managedCenters()->pluck('centers.id')->all())->toBe([$org['centerB']->id])
        ->and($org['centerA']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$org['centerManager']->id]);

    $centerC = Center::query()->create([
        'scheme_id' => $org['projectB']->id,
        'name' => 'Center C',
        'code' => 'CC',
        'is_active' => true,
    ]);
    $org['director']->directedCenters()->attach($centerC->id);

    Livewire::actingAs($org['director'])
        ->test(CreateOrgUser::class)
        ->fillForm([
            'role' => UserRole::CenterManager->value,
            'name' => 'Director Created Manager',
            'login_id' => 'dircm01',
            'email' => 'dircm01@fieldtrack.local',
            'password' => 'CenterMgr@123',
            'managedCenters' => [$centerC->id],
            'is_active' => true,
            'must_change_password' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $directorManager = User::query()->where('login_id', 'dircm01')->firstOrFail();
    expect($directorManager->role)->toBe(UserRole::CenterManager->value)
        ->and($directorManager->managedCenters()->pluck('centers.id')->all())->toBe([$centerC->id]);
});

it('still lets a coordinator edit an in-scope manager without the removed button', function () {
    $org = seedOrg();
    $coordinator = createManagerCoordinator('Assign Coord', '9123456940');
    $coordinator->coordinatedCenters()->attach([$org['centerA']->id, $org['centerB']->id]);
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

    $this->actingAs($coordinator)
        ->get(OrgUserResource::getUrl('edit', ['record' => $outsideOnly]))
        ->assertNotFound();

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
        ->and($org['centerA']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([])
        ->and($third->fresh()->primary_center_manager_id)->toBe($outsideOnly->id)
        ->and($coordinator->fresh()->coordinatedCenters()->pluck('centers.id')->all())
        ->toEqualCanonicalizing([$org['centerA']->id, $org['centerB']->id]);
});
