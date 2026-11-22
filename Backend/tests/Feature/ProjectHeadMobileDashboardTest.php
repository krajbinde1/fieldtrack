<?php

use App\Enums\UserRole;
use App\Filament\Resources\OrgUsers\Pages\EditOrgUser;
use App\Services\OrganizationAccessService;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

it('keeps a project manager record without mobile or organization access', function () {
    $org = seedOrg();
    $projectHead = $org['projectHead']->fresh();

    expect($projectHead->role)->toBe(UserRole::ProjectHead->value)
        ->and($projectHead->headedCenters()->pluck('centers.id')->all())->toContain($org['centerA']->id)
        ->and($projectHead->roleEnum()->canAccessWeb())->toBeFalse()
        ->and($projectHead->canLoginToMobile())->toBeFalse()
        ->and(app(OrganizationAccessService::class)->visibleCenterIds($projectHead))->toBe([])
        ->and(app(OrganizationAccessService::class)->creatableOrgUserRoles($projectHead))->toBe([]);

    Sanctum::actingAs($projectHead);

    $this->getJson('/api/me')->assertForbidden();
    $this->getJson('/api/dashboard')->assertForbidden();
    $this->getJson('/api/director/centers')->assertForbidden();
    $this->getJson('/api/manager/employees')->assertForbidden();
    $this->postJson('/api/attendance/punch-in', [
        'latitude' => 18.52,
        'longitude' => 73.85,
        'location_address' => 'Pune',
    ])->assertForbidden();

    expect($projectHead->fresh()->headedCenters()->pluck('centers.id')->all())->toContain($org['centerA']->id);
});

it('deactivates existing project manager accounts without deleting their records', function () {
    $org = seedOrg();
    $org['projectHead']->createToken('mobile-session');

    $migration = require database_path('migrations/2026_11_22_120000_deactivate_project_manager_accounts.php');
    $migration->up();

    $fresh = $org['projectHead']->fresh();

    expect($fresh)->not->toBeNull()
        ->and($fresh->role)->toBe(UserRole::ProjectHead->value)
        ->and($fresh->is_active)->toBeFalse()
        ->and($fresh->tokens()->count())->toBe(0)
        ->and($fresh->headedCenters()->pluck('centers.id')->all())->toContain($org['centerA']->id)
        ->and($fresh->canLoginToMobile())->toBeFalse()
        ->and($fresh->canAccessPanel(filament()->getPanel('admin')))->toBeFalse();
});

it('lets an admin reassign a former project manager instead of granting that role access', function () {
    $org = seedOrg();

    Livewire::actingAs($org['admin'])
        ->test(EditOrgUser::class, ['record' => $org['projectHead']->getKey()])
        ->fillForm([
            'role' => UserRole::CenterManager->value,
            'managedCenters' => [$org['centerB']->id],
            'is_active' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $fresh = $org['projectHead']->fresh();

    expect($fresh->role)->toBe(UserRole::CenterManager->value)
        ->and($fresh->is_active)->toBeTrue()
        ->and($fresh->managedCenters()->pluck('centers.id')->all())->toContain($org['centerB']->id)
        ->and($fresh->headedCenters()->pluck('centers.id')->all())->toContain($org['centerA']->id)
        ->and($fresh->canLoginToMobile())->toBeTrue();
});
