<?php

use App\Enums\UserRole;
use App\Filament\Resources\Centers\Pages\EditCenter;
use App\Filament\Resources\Centers\Pages\ListCenters;
use App\Models\User;
use App\Services\OrganizationAccessService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

function assignmentTestCoordinator(string $name, string $loginId): User
{
    return User::query()->create([
        'name' => $name,
        'email' => $loginId.'@fieldtrack.local',
        'login_id' => $loginId,
        'password' => Hash::make('1234'),
        'role' => UserRole::CenterCoordinator->value,
        'is_active' => true,
        'is_center_login' => false,
    ]);
}

it('assigns coordinators from the list modal and the edit form without changing managers', function () {
    $org = seedOrg();
    $first = assignmentTestCoordinator('Coord One', '9123456811');
    $second = assignmentTestCoordinator('Coord Two', '9123456812');
    $managerIds = $org['centerA']->centerManagers()->pluck('users.id')->all();
    $primaryId = $org['centerA']->primary_center_manager_id;
    $loginUserId = $org['centerA']->login_user_id;

    $list = Livewire::actingAs($org['admin'])
        ->test(ListCenters::class)
        ->assertSee('Not Assigned')
        ->assertActionVisible(TestAction::make('assignCoordinators')->table($org['centerB']));

    expect($list->html())->toMatch(
        '/mountTableAction\(&#039;assignCoordinators&#039;, &#039;[^&]+&#039;\)[\s\S]{0,2500}Not Assigned/',
    );

    $list->mountAction(TestAction::make('assignCoordinators')->table($org['centerB']));

    $mounted = $list->instance()->getMountedAction();
    expect($mounted)->not->toBeNull()
        ->and((string) $mounted->getModalHeading())->toBe('Assign Coordinators')
        ->and($list->instance()->mountedActionShouldOpenModal())->toBeTrue();

    $list
        ->assertFormFieldVisible('coordinators')
        ->assertFormFieldExists('coordinators', function ($field): bool {
            return $field->isMultiple() && $field->isSearchable();
        });

    $preselected = collect(data_get($list->instance(), 'mountedActions.0.data.coordinators'))
        ->map(fn ($id): int => (int) $id)
        ->all();
    expect($preselected)->toBe([]);

    $list->fillForm([
        'coordinators' => [$first->id, $second->id],
    ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($org['centerB']->fresh()->coordinators()->pluck('users.id')->all())
        ->toEqualCanonicalizing([$first->id, $second->id])
        ->and($org['centerA']->fresh()->centerManagers()->pluck('users.id')->all())->toBe($managerIds)
        ->and($org['centerA']->fresh()->primary_center_manager_id)->toBe($primaryId)
        ->and($org['centerA']->fresh()->login_user_id)->toBe($loginUserId)
        ->and($first->fresh()->coordinatedCenters()->pluck('centers.id')->all())->toContain($org['centerB']->id);

    Livewire::actingAs($org['admin'])
        ->test(ListCenters::class)
        ->assertSee('Coord One')
        ->assertSee('Coord Two');

    Livewire::actingAs($org['admin'])
        ->test(ListCenters::class)
        ->callAction(TestAction::make('assignCoordinators')->table($org['centerA']), [
            'coordinators' => [$first->id],
        ])
        ->assertHasNoFormErrors();

    expect($first->fresh()->coordinatedCenters()->pluck('centers.id')->all())
        ->toContain($org['centerA']->id, $org['centerB']->id)
        ->and($org['centerA']->fresh()->centerManagers()->pluck('users.id')->all())->toBe($managerIds);

    Livewire::actingAs($org['admin'])
        ->test(ListCenters::class)
        ->callAction(TestAction::make('assignCoordinators')->table($org['centerB']), [
            'coordinators' => [],
        ])
        ->assertHasNoFormErrors();

    expect($org['centerB']->fresh()->coordinators()->pluck('users.id')->all())->toBe([])
        ->and($org['centerA']->fresh()->coordinators()->pluck('users.id')->all())->toBe([$first->id])
        ->and($first->fresh()->coordinatedCenters()->pluck('centers.id')->all())->toBe([$org['centerA']->id])
        ->and($org['centerA']->fresh()->centerManagers()->pluck('users.id')->all())->toBe($managerIds);

    Livewire::actingAs($org['admin'])
        ->test(ListCenters::class)
        ->assertSee('Not Assigned')
        ->assertSee('Coord One')
        ->assertDontSee('Coord Two');

    $edit = Livewire::actingAs($org['admin'])
        ->test(EditCenter::class, ['record' => $org['centerA']->getKey()])
        ->assertFormFieldVisible('coordinators')
        ->assertFormFieldVisible('centerManagers')
        ->assertSee('Center Login ID');

    $editIds = collect($edit->get('data.coordinators'))->map(fn ($id): int => (int) $id)->all();
    expect($editIds)->toEqualCanonicalizing([$first->id]);

    $edit->fillForm([
        'coordinators' => [$first->id, $second->id],
        'centerManagers' => $managerIds,
    ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($org['centerA']->fresh()->coordinators()->pluck('users.id')->all())
        ->toEqualCanonicalizing([$first->id, $second->id])
        ->and($org['centerA']->fresh()->centerManagers()->pluck('users.id')->all())->toBe($managerIds)
        ->and($org['centerB']->fresh()->coordinators()->pluck('users.id')->all())->toBe([]);
});

it('lets a director assign coordinators and rejects everyone else', function () {
    $org = seedOrg();
    $coordinator = assignmentTestCoordinator('Scoped Coord', '9123456821');
    $other = assignmentTestCoordinator('Other Coord', '9123456822');
    $org['centerA']->coordinators()->sync([$coordinator->id]);
    $managerIds = $org['centerA']->centerManagers()->pluck('users.id')->all();

    $coordinatorList = Livewire::actingAs($coordinator)
        ->test(ListCenters::class);

    $coordinatorList
        ->assertActionHidden(TestAction::make('assignCoordinators')->table($org['centerA']))
        ->assertDontSee('assignCoordinators')
        ->call('mountTableAction', 'assignCoordinators', (string) $org['centerA']->getKey())
        ->set('mountedActions', [[
            'name' => 'assignCoordinators',
            'arguments' => [],
            'context' => [
                'table' => true,
                'recordKey' => (string) $org['centerA']->getKey(),
            ],
        ]])
        ->set('mountedActions.0.data.coordinators', [(string) $other->id])
        ->call('callMountedAction');

    expect($org['centerA']->fresh()->coordinators()->pluck('users.id')->all())->toBe([$coordinator->id]);

    Livewire::actingAs($coordinator)
        ->test(EditCenter::class, ['record' => $org['centerA']->getKey()])
        ->assertForbidden();

    Livewire::actingAs($org['centerManager'])
        ->test(ListCenters::class)
        ->assertActionHidden(TestAction::make('assignCoordinators')->table($org['centerA']));

    Livewire::actingAs($org['centerManager'])
        ->test(EditCenter::class, ['record' => $org['centerA']->getKey()])
        ->assertForbidden();

    Livewire::actingAs($org['projectHead'])
        ->test(ListCenters::class)
        ->assertActionHidden(TestAction::make('assignCoordinators')->table($org['centerA']));

    Livewire::actingAs($org['projectHead'])
        ->test(EditCenter::class, ['record' => $org['centerA']->getKey()])
        ->assertFormFieldHidden('coordinators')
        ->assertFormFieldVisible('centerManagers')
        ->fillForm([
            'coordinators' => [$other->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($org['centerA']->fresh()->coordinators()->pluck('users.id')->all())->toBe([$coordinator->id])
        ->and($org['centerA']->fresh()->centerManagers()->pluck('users.id')->all())->toBe($managerIds);

    Livewire::actingAs($org['director'])
        ->test(ListCenters::class)
        ->assertActionVisible(TestAction::make('assignCoordinators')->table($org['centerB']))
        ->callAction(TestAction::make('assignCoordinators')->table($org['centerB']), [
            'coordinators' => [$coordinator->id, $other->id],
        ])
        ->assertHasNoFormErrors();

    expect($org['centerB']->fresh()->coordinators()->pluck('users.id')->all())
        ->toEqualCanonicalizing([$coordinator->id, $other->id])
        ->and($coordinator->fresh()->coordinatedCenters()->pluck('centers.id')->all())
        ->toContain($org['centerA']->id, $org['centerB']->id)
        ->and($org['centerA']->fresh()->centerManagers()->pluck('users.id')->all())->toBe($managerIds);
});

it('updates a coordinator center list as soon as the assignment changes', function () {
    $org = seedOrg();
    $coordinator = assignmentTestCoordinator('Live Coord', '9123456831');
    $access = app(OrganizationAccessService::class);

    expect($access->visibleCenterIds($coordinator))->toBe([]);

    Livewire::actingAs($coordinator)
        ->test(ListCenters::class)
        ->assertDontSee('Center B');

    Livewire::actingAs($org['admin'])
        ->test(ListCenters::class)
        ->callAction(TestAction::make('assignCoordinators')->table($org['centerB']), [
            'coordinators' => [$coordinator->id],
        ])
        ->assertHasNoFormErrors();

    expect($access->visibleCenterIds($coordinator->fresh()))->toBe([$org['centerB']->id]);

    Livewire::actingAs($coordinator)
        ->test(ListCenters::class)
        ->assertSee('Center B')
        ->assertDontSee('Center A');

    Livewire::actingAs($org['admin'])
        ->test(ListCenters::class)
        ->callAction(TestAction::make('assignCoordinators')->table($org['centerB']), [
            'coordinators' => [],
        ])
        ->assertHasNoFormErrors();

    expect($access->visibleCenterIds($coordinator->fresh()))->toBe([]);

    Livewire::actingAs($coordinator)
        ->test(ListCenters::class)
        ->assertDontSee('Center B');
});
