<?php

use App\Enums\FieldActivityType;
use App\Enums\UserRole;
use App\Filament\Resources\Centers\CenterResource;
use App\Filament\Resources\Centers\Pages\CreateCenter;
use App\Filament\Resources\Centers\Pages\EditCenter;
use App\Models\Center;
use App\Models\FieldActivity;
use App\Models\Scheme;
use App\Models\User;
use App\Services\OrganizationAccessService;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

it('lets an admin delete an unused scheme from the center form and keeps other fields', function () {
    $org = seedOrg();
    $centerCount = Center::query()->count();
    $userCount = User::query()->count();
    $employeeCount = $org['centerA']->employees()->count();

    $component = Livewire::actingAs($org['admin'])
        ->test(CreateCenter::class)
        ->assertActionVisible(TestAction::make('deleteScheme')->schemaComponent('scheme_id'))
        ->assertActionHasLabel(
            TestAction::make('deleteScheme')->schemaComponent('scheme_id'),
            'Delete Scheme',
        )
        ->assertActionVisible(TestAction::make('createOption')->schemaComponent('scheme_id'))
        ->fillForm([
            'name' => 'Center With Throwaway Scheme',
            'address' => '12 Field Road',
            'is_active' => false,
        ])
        ->callAction(TestAction::make('createOption')->schemaComponent('scheme_id'), [
            'name' => 'Throwaway Scheme',
            'code' => 'THS',
            'description' => 'Unused',
            'is_active' => true,
        ])
        ->assertHasNoActionErrors()
        ->assertSet('data.name', 'Center With Throwaway Scheme')
        ->assertSet('data.address', '12 Field Road');

    $scheme = Scheme::query()->where('code', 'THS')->firstOrFail();

    $component
        ->assertSet('data.scheme_id', $scheme->id)
        ->callAction(TestAction::make('deleteScheme')->schemaComponent('scheme_id'))
        ->assertNotified(Scheme::DELETED_MESSAGE)
        ->assertSet('data.name', 'Center With Throwaway Scheme')
        ->assertSet('data.address', '12 Field Road')
        ->assertSet('data.is_active', false)
        ->assertSet('data.scheme_id', null)
        ->assertFormFieldExists('scheme_id', function (Select $field) use ($scheme, $org): bool {
            $ids = array_map(strval(...), array_keys($field->getOptions() ?? []));

            return $field->hasDynamicOptions()
                && ! in_array((string) $scheme->id, $ids, true)
                && in_array((string) $org['projectA']->id, $ids, true)
                && in_array((string) $org['projectB']->id, $ids, true);
        });

    expect(Scheme::query()->whereKey($scheme->id)->exists())->toBeFalse()
        ->and(Scheme::query()->whereKey($org['projectA']->id)->exists())->toBeTrue()
        ->and(Scheme::query()->whereKey($org['projectB']->id)->exists())->toBeTrue()
        ->and(Center::query()->count())->toBe($centerCount)
        ->and(User::query()->count())->toBe($userCount)
        ->and($org['centerA']->fresh()->scheme_id)->toBe($org['projectA']->id)
        ->and($org['centerB']->fresh()->scheme_id)->toBe($org['projectB']->id)
        ->and($org['centerA']->fresh()->employees()->count())->toBe($employeeCount)
        ->and(Center::query()->where('name', 'Center With Throwaway Scheme')->exists())->toBeFalse();
});

it('lets a director delete an unused scheme and blocks one that has a center', function () {
    $org = seedOrg();
    $unused = Scheme::query()->create([
        'name' => 'Director Unused',
        'code' => 'DU',
        'is_active' => true,
    ]);
    $centerCount = Center::query()->count();

    $this->actingAs($org['director']);

    expect(CenterResource::canDeleteSchemes())->toBeTrue()
        ->and(CenterResource::deleteUnusedScheme($unused->id))->toBeTrue()
        ->and(Scheme::query()->whereKey($unused->id)->exists())->toBeFalse()
        ->and(Center::query()->count())->toBe($centerCount)
        ->and($org['centerA']->fresh()->scheme_id)->toBe($org['projectA']->id);

    $options = app(OrganizationAccessService::class)
        ->schemeQuery($org['director'])
        ->pluck('id')
        ->map(fn ($id) => (int) $id)
        ->all();

    expect($options)->not->toContain($unused->id)
        ->and($options)->toContain($org['projectA']->id);

    $blocked = Scheme::query()->create([
        'name' => 'Director Blocked',
        'code' => 'DB',
        'is_active' => true,
    ]);
    $blockingCenter = Center::query()->create([
        'scheme_id' => $blocked->id,
        'name' => 'Blocks Director Delete',
        'is_active' => true,
    ]);

    expect(CenterResource::deleteUnusedScheme($blocked->id))->toBeFalse()
        ->and(Scheme::query()->whereKey($blocked->id)->exists())->toBeTrue()
        ->and($blockingCenter->fresh())->not->toBeNull()
        ->and($blockingCenter->fresh()->scheme_id)->toBe($blocked->id);
});

it('rejects deletion when the scheme is used and leaves related rows in place', function () {
    $org = seedOrg();
    $centerCount = Center::query()->count();
    $userCount = User::query()->count();

    Livewire::actingAs($org['admin'])
        ->test(EditCenter::class, ['record' => $org['centerA']->getKey()])
        ->fillForm([
            'name' => 'Center A Kept',
            'address' => 'Kept Address',
        ])
        ->callAction(TestAction::make('deleteScheme')->schemaComponent('scheme_id'))
        ->assertNotified(Scheme::DELETE_BLOCKED_MESSAGE)
        ->assertSet('data.name', 'Center A Kept')
        ->assertSet('data.address', 'Kept Address')
        ->assertSet('data.scheme_id', $org['projectA']->id);

    expect(Scheme::query()->whereKey($org['projectA']->id)->exists())->toBeTrue()
        ->and($org['centerA']->fresh()->scheme_id)->toBe($org['projectA']->id)
        ->and($org['centerA']->fresh()->name)->toBe('Center A')
        ->and(Center::query()->count())->toBe($centerCount)
        ->and(User::query()->count())->toBe($userCount)
        ->and($org['centerB']->fresh()->scheme_id)->toBe($org['projectB']->id);

    $activityScheme = Scheme::query()->create([
        'name' => 'Activity Only',
        'code' => 'AO',
        'is_active' => true,
    ]);
    $activity = FieldActivity::query()->create([
        'employee_id' => $org['empA']->id,
        'user_id' => $org['userA']->id,
        'center_id' => null,
        'scheme_id' => $activityScheme->id,
        'activity_type' => FieldActivityType::Other,
        'activity_name' => 'Unused center link',
        'activity_at' => now(),
        'photo_path' => 'field-activities/keep.jpg',
        'latitude' => 18.52,
        'longitude' => 73.85,
    ]);

    Livewire::actingAs($org['admin'])
        ->test(CreateCenter::class)
        ->fillForm([
            'scheme_id' => $activityScheme->id,
            'name' => 'Name Stays',
            'address' => 'Address Stays',
        ])
        ->callAction(TestAction::make('deleteScheme')->schemaComponent('scheme_id'))
        ->assertNotified(Scheme::DELETE_BLOCKED_MESSAGE)
        ->assertSet('data.name', 'Name Stays')
        ->assertSet('data.address', 'Address Stays')
        ->assertSet('data.scheme_id', $activityScheme->id);

    expect(Scheme::query()->whereKey($activityScheme->id)->exists())->toBeTrue()
        ->and(FieldActivity::query()->whereKey($activity->id)->exists())->toBeTrue()
        ->and($activity->fresh()->scheme_id)->toBe($activityScheme->id)
        ->and(Center::query()->count())->toBe($centerCount)
        ->and($org['empA']->fresh())->not->toBeNull();
});

it('hides and forbids scheme deletion for coordinators, project heads, and center managers', function () {
    $org = seedOrg();
    $unused = Scheme::query()->create([
        'name' => 'Protected Scheme',
        'code' => 'PRS',
        'is_active' => true,
    ]);
    $coordinator = User::query()->create([
        'name' => 'Coord',
        'email' => 'coord-delete@test.local',
        'login_id' => 'coorddelete',
        'password' => Hash::make('secret'),
        'role' => UserRole::CenterCoordinator->value,
        'is_active' => true,
    ]);

    Livewire::actingAs($org['projectHead'])
        ->test(CreateCenter::class)
        ->assertForbidden();

    expect(Scheme::query()->whereKey($unused->id)->exists())->toBeTrue()
        ->and(Scheme::query()->whereKey($org['projectA']->id)->exists())->toBeTrue();

    foreach ([$org['centerManager'], $coordinator, $org['projectHead']] as $user) {
        $this->actingAs($user);

        expect(CenterResource::canDeleteSchemes())->toBeFalse();

        try {
            CenterResource::deleteUnusedScheme($unused->id);
            $thrown = null;
        } catch (HttpException $exception) {
            $thrown = $exception->getStatusCode();
        }

        expect($thrown)->toBe(403)
            ->and(Scheme::query()->whereKey($unused->id)->exists())->toBeTrue();
    }
});
