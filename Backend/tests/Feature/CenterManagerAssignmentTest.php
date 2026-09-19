<?php

use App\Enums\UserRole;
use App\Filament\Resources\Centers\Pages\CreateCenter;
use App\Filament\Resources\Centers\Pages\EditCenter;
use App\Filament\Resources\Centers\Pages\ListCenters;
use App\Filament\Resources\OrgUsers\Pages\CreateOrgUser;
use App\Models\Center;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('assigns center managers from the user form and reflects them on the center list', function () {
    $org = seedOrg();

    Livewire::actingAs($org['admin'])
        ->test(CreateCenter::class)
        ->assertFormFieldDoesNotExist('centerManagers')
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
        ->assertFormFieldDoesNotExist('centerManagers');

    Livewire::actingAs($org['admin'])
        ->test(ListCenters::class)
        ->assertSee('Assigned CM')
        ->assertSee('CM');
});
