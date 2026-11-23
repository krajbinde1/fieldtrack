<?php

use App\Enums\CenterStaffRole;
use App\Enums\UserRole;
use App\Filament\Resources\Centers\Pages\EditCenter;
use App\Filament\Resources\Centers\Pages\ListCenters;
use App\Models\User;
use App\Services\OrganizationAccessService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

function managerAssignmentCoordinator(string $name, string $loginId): User
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

it('lets an assigned coordinator set and replace only their center manager', function () {
    $org = seedOrg();
    $coordinator = managerAssignmentCoordinator('Assigned Coord', '9123456811');
    $coordinator->coordinatedCenters()->attach($org['centerA']->id);
    $replacement = User::query()->create([
        'name' => 'Replacement CM',
        'email' => 'replacementcm@fieldtrack.local',
        'login_id' => '9123456812',
        'password' => Hash::make('6812'),
        'role' => UserRole::CenterManager->value,
        'is_active' => true,
        'is_center_login' => false,
    ]);
    $originalManagers = $org['centerA']->centerManagers()->pluck('users.id')->all();
    $coordinatorIds = $org['centerA']->coordinators()->pluck('users.id')->all();

    expect(app(OrganizationAccessService::class)->visibleCenterIds($coordinator->fresh()))->toBe([$org['centerA']->id])
        ->and(app(OrganizationAccessService::class)->canManageCenterManagers($coordinator, $org['centerA']))->toBeTrue()
        ->and(app(OrganizationAccessService::class)->canManageCenterManagers($coordinator, $org['centerB']))->toBeFalse()
        ->and(app(OrganizationAccessService::class)->canManageCenters($coordinator))->toBeFalse()
        ->and(app(OrganizationAccessService::class)->canManageEmployees($coordinator))->toBeFalse();

    Livewire::actingAs($coordinator)
        ->test(EditCenter::class, ['record' => $org['centerA']->getKey()])
        ->assertForbidden();

    $list = Livewire::actingAs($coordinator)
        ->test(ListCenters::class)
        ->assertActionVisible(TestAction::make('assignManager')->table($org['centerA']))
        ->mountAction(TestAction::make('assignManager')->table($org['centerA']))
        ->assertActionHasLabel(
            TestAction::make('createOption')->schemaComponent('centerManagers'),
            '+ Create New Center Manager',
        )
        ->unmountAction();

    $list->callAction(TestAction::make('assignManager')->table($org['centerA']), [
        'centerManagers' => [(string) $replacement->id],
    ])
        ->assertHasNoActionErrors();

    expect($org['centerA']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$replacement->id])
        ->and($org['centerA']->fresh()->primary_center_manager_id)->toBe($replacement->id)
        ->and($org['centerB']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([])
        ->and($org['centerA']->fresh()->coordinators()->pluck('users.id')->all())->toBe($coordinatorIds)
        ->and($replacement->fresh()->managedCenters()->pluck('centers.id')->all())->toBe([$org['centerA']->id])
        ->and($org['centerManager']->fresh()->managedCenters()->pluck('centers.id')->all())->not->toContain($org['centerA']->id);

    Livewire::actingAs($coordinator)
        ->test(ListCenters::class)
        ->callAction(TestAction::make('assignManager')->table($org['centerA']), [
            'centerManagers' => [(string) $org['centerManager']->id],
        ])
        ->assertHasNoActionErrors();

    expect($org['centerA']->fresh()->centerManagers()->pluck('users.id')->all())->toBe($originalManagers)
        ->and($org['centerA']->fresh()->coordinators()->pluck('users.id')->all())->toBe($coordinatorIds)
        ->and($replacement->fresh()->managedCenters()->pluck('centers.id')->all())->toBe([]);

    $blocked = false;
    try {
        Livewire::actingAs($coordinator)
            ->test(ListCenters::class)
            ->callAction(TestAction::make('assignManager')->table($org['centerB']), [
                'centerManagers' => [(string) $replacement->id],
            ]);
    } catch (Throwable) {
        $blocked = true;
    }

    expect($blocked)->toBeTrue()
        ->and($org['centerB']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([])
        ->and($replacement->fresh()->managedCenters()->pluck('centers.id')->all())->toBe([]);
});

it('lets one manager be assigned to a second center and rejects two active managers', function () {
    $org = seedOrg();
    $coordinator = managerAssignmentCoordinator('Multi Coord', '9123456821');
    $coordinator->coordinatedCenters()->attach([$org['centerA']->id, $org['centerB']->id]);
    $manager = User::query()->create([
        'name' => 'Shared CM',
        'email' => 'sharedcm@fieldtrack.local',
        'login_id' => '9123456822',
        'password' => Hash::make('6822'),
        'role' => UserRole::CenterManager->value,
        'is_active' => true,
        'is_center_login' => false,
    ]);

    Livewire::actingAs($coordinator)
        ->test(ListCenters::class)
        ->callAction(TestAction::make('assignManager')->table($org['centerA']), [
            'centerManagers' => [(string) $manager->id],
        ])
        ->assertHasNoActionErrors();

    Livewire::actingAs($coordinator)
        ->test(ListCenters::class)
        ->callAction(TestAction::make('assignManager')->table($org['centerB']), [
            'centerManagers' => [(string) $manager->id],
        ])
        ->assertHasNoActionErrors();

    expect($org['centerA']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$manager->id])
        ->and($org['centerB']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$manager->id])
        ->and($manager->fresh()->managedCenters()->pluck('centers.id')->all())
        ->toEqualCanonicalizing([$org['centerA']->id, $org['centerB']->id])
        ->and(User::query()->where('login_id', '9123456822')->count())->toBe(1);

    Livewire::actingAs($org['admin'])
        ->test(EditCenter::class, ['record' => $org['centerB']->getKey()])
        ->fillForm([
            'centerManagers' => [$manager->id, $org['centerManager']->id],
        ])
        ->call('save')
        ->assertHasErrors(['centerManagers' => 'A center can have only one active Center Manager.']);

    expect($org['centerB']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$manager->id])
        ->and($org['centerA']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$manager->id]);
});

it('reuses an existing center manager from the modal and rejects another role', function () {
    $org = seedOrg();
    $coordinator = managerAssignmentCoordinator('Reuse Coord', '9123456831');
    $coordinator->coordinatedCenters()->attach($org['centerB']->id);
    $existing = User::query()->create([
        'name' => 'Existing CM',
        'email' => 'existingcm@fieldtrack.local',
        'login_id' => '9123456832',
        'password' => Hash::make('Existing@123'),
        'role' => UserRole::CenterManager->value,
        'is_active' => true,
        'is_center_login' => false,
        'must_change_password' => false,
    ]);
    $password = $existing->getRawOriginal('password');

    Livewire::actingAs($coordinator)
        ->test(ListCenters::class)
        ->mountAction(TestAction::make('assignManager')->table($org['centerB']))
        ->callAction(
            TestAction::make('createOption')->schemaComponent('centerManagers'),
            [
                'name' => 'Should Not Duplicate',
                'mobile' => '9123456832',
                'email' => null,
            ],
        )
        ->assertHasNoActionErrors()
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(User::query()->where('login_id', '9123456832')->count())->toBe(1)
        ->and(User::query()->where('name', 'Should Not Duplicate')->exists())->toBeFalse()
        ->and($existing->fresh()->getRawOriginal('password'))->toBe($password)
        ->and($existing->fresh()->must_change_password)->toBeFalse()
        ->and($org['centerB']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$existing->id])
        ->and($org['centerA']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$org['centerManager']->id]);

    $otherRole = User::query()->create([
        'name' => 'Not A Manager',
        'email' => 'notamanager@fieldtrack.local',
        'login_id' => '9123456833',
        'password' => Hash::make('6833'),
        'role' => UserRole::CenterCoordinator->value,
        'is_active' => true,
        'is_center_login' => false,
    ]);

    Livewire::actingAs($org['director'])
        ->test(EditCenter::class, ['record' => $org['centerB']->getKey()])
        ->fillForm(['address' => 'Director Kept'])
        ->callAction(TestAction::make('createOption')->schemaComponent('centerManagers'), [
            'name' => 'Rejected Manager',
            'mobile' => '9123456833',
            'email' => null,
        ])
        ->assertHasActionErrors(['mobile'])
        ->assertSet('data.address', 'Director Kept');

    expect(User::query()->where('name', 'Rejected Manager')->exists())->toBeFalse()
        ->and(User::query()->whereKey($otherRole->id)->exists())->toBeTrue()
        ->and($org['centerB']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$existing->id]);
});

it('lets an assigned coordinator create a center manager account for that center only', function () {
    $org = seedOrg();
    $coordinator = managerAssignmentCoordinator('Creator Coord', '9123456841');
    $coordinator->coordinatedCenters()->attach($org['centerA']->id);
    $org['centerA']->centerManagers()->detach();
    $org['centerA']->forceFill(['primary_center_manager_id' => null])->save();
    $beforeManagers = User::query()->where('role', UserRole::CenterManager->value)->count();

    $list = Livewire::actingAs($coordinator)
        ->test(ListCenters::class)
        ->mountAction(TestAction::make('assignManager')->table($org['centerA']))
        ->callAction(
            TestAction::make('createOption')->schemaComponent('centerManagers'),
            [
                'name' => 'Created By Coord',
                'mobile' => '9123456842',
                'email' => null,
            ],
        )
        ->assertHasNoActionErrors();

    expect(User::query()->where('login_id', '9123456842')->exists())->toBeFalse()
        ->and(json_encode($list->instance()->pendingManagers))->not->toContain('"password"');

    $list->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Center Manager created. Login ID: 9123456842. Temporary password: 6842.');

    $manager = User::query()->where('login_id', '9123456842')->firstOrFail();

    expect($manager->role)->toBe(UserRole::CenterManager->value)
        ->and($manager->is_center_login)->toBeFalse()
        ->and($manager->must_change_password)->toBeTrue()
        ->and(Hash::check('6842', $manager->password))->toBeTrue()
        ->and($manager->getRawOriginal('password'))->not->toBe('6842')
        ->and($org['centerA']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([$manager->id])
        ->and($org['centerB']->fresh()->centerManagers()->pluck('users.id')->all())->toBe([])
        ->and($org['centerA']->fresh()->coordinators()->pluck('users.id')->all())->toBe([$coordinator->id])
        ->and(User::query()->where('role', UserRole::CenterManager->value)->count())->toBe($beforeManagers + 1);

    $blocked = false;
    try {
        Livewire::actingAs($coordinator)
            ->test(ListCenters::class)
            ->callAction(TestAction::make('assignManager')->table($org['centerB']), [
                'centerManagers' => [(string) $manager->id],
            ]);
    } catch (Throwable) {
        $blocked = true;
    }

    expect($blocked)->toBeTrue()
        ->and($manager->fresh()->managedCenters()->pluck('centers.id')->all())->toBe([$org['centerA']->id]);
});

it('keeps trainer mobilizer and the combined food and hostel supervisor assignable by a center manager', function () {
    expect(CenterStaffRole::assignableOptions())->toBe([
        CenterStaffRole::Trainer->value => 'Trainer',
        CenterStaffRole::Mobilizer->value => 'Mobilizer',
        CenterStaffRole::FoodHostelSupervisor->value => 'Food & Hostel Supervisor',
    ])
        ->and(CenterStaffRole::tryFrom('food_supervisor'))->toBeNull()
        ->and(CenterStaffRole::tryFrom('hostel_supervisor'))->toBeNull();

    $org = seedOrg();
    $access = app(OrganizationAccessService::class);

    expect($access->canManageEmployees($org['centerManager'], $org['centerA']))->toBeTrue()
        ->and($access->canManageEmployees($org['centerManager'], $org['centerB']))->toBeFalse()
        ->and($access->visibleCenterIds($org['centerManager']))->toBe([$org['centerA']->id])
        ->and($access->hasOrganizationWideAccess($org['admin']))->toBeTrue()
        ->and($access->hasOrganizationWideAccess($org['director']))->toBeFalse();
});
