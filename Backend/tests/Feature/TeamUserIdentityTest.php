<?php

use App\Enums\CenterStaffRole;
use App\Enums\UserRole;
use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Models\Employee;
use App\Models\User;
use App\Services\DuplicateUserReport;
use App\Support\UserIdentity;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function identityCoordinator(array $org): User
{
    $coordinator = User::query()->create([
        'name' => 'Coordinator',
        'email' => 'coord-identity@test.local',
        'login_id' => 'coordidentity',
        'password' => Hash::make('Coordinator@123'),
        'role' => UserRole::CenterCoordinator->value,
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $coordinator->coordinatedCenters()->attach([$org['centerA']->id, $org['centerB']->id]);

    return $coordinator;
}

it('reuses one account when the same person is assigned to another center', function () {
    $org = seedOrg();
    $coordinator = identityCoordinator($org);

    Livewire::actingAs($coordinator)
        ->test(CreateEmployee::class)
        ->fillForm([
            'center_id' => $org['centerA']->id,
            'full_name' => 'Shared Person',
            'mobile' => '9123456709',
            'staff_role' => CenterStaffRole::Mobilizer->value,
            'travel_km_rate' => 5,
            'monthly_km_limit' => 300,
            'status' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::actingAs($coordinator)
        ->test(CreateEmployee::class)
        ->fillForm([
            'center_id' => $org['centerB']->id,
            'full_name' => '  shared   person ',
            'mobile' => '9123456709',
            'staff_role' => CenterStaffRole::Mobilizer->value,
            'travel_km_rate' => 5,
            'monthly_km_limit' => 300,
            'status' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(collect(session('filament.notifications'))->pluck('title'))
        ->toContain('Existing user assigned to this center.');

    $employees = Employee::query()->where('mobile', '9123456709')->get();
    $users = User::query()->where('login_id', '9123456709')->get();
    $employee = $employees->first();

    expect($employees)->toHaveCount(1)
        ->and($users)->toHaveCount(1)
        ->and($employee->user?->is($users->first()))->toBeTrue()
        ->and($employee->assignedCenterNames())->toBe(['Center A', 'Center B'])
        ->and($employee->center_id)->toBe($org['centerA']->id);

    $list = Livewire::actingAs($coordinator)
        ->test(ListEmployees::class)
        ->assertCanSeeTableRecords([$employee])
        ->assertSee('Center A')
        ->assertSee('Center B');

    $tree = $list->call('setLayout', 'hierarchy')->instance()->hierarchyTree();
    $rows = collect($tree)->flatMap(fn (array $center): array => $center['employees']);
    $copies = $rows->where('id', $employee->id);

    expect($copies)->toHaveCount(2)
        ->and($copies->pluck('kind')->unique()->all())->toBe(['employee']);
});

it('rejects a second account with the same mobile or the same name and mobile', function () {
    $org = seedOrg();
    $coordinator = identityCoordinator($org);

    Livewire::actingAs($coordinator)
        ->test(CreateEmployee::class)
        ->fillForm([
            'center_id' => $org['centerA']->id,
            'full_name' => 'First Person',
            'mobile' => '9123456710',
            'staff_role' => CenterStaffRole::Mobilizer->value,
            'travel_km_rate' => 5,
            'monthly_km_limit' => 300,
            'status' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::actingAs($coordinator)
        ->test(CreateEmployee::class)
        ->fillForm([
            'center_id' => $org['centerB']->id,
            'full_name' => 'Someone Else',
            'mobile' => '9123456710',
            'staff_role' => CenterStaffRole::Mobilizer->value,
            'travel_km_rate' => 5,
            'monthly_km_limit' => 300,
            'status' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['mobile']);

    expect(User::query()->whereRaw('lower(login_id) = ?', ['9123456710'])->count())->toBe(1);

    $employee = Employee::query()->where('mobile', '9123456710')->firstOrFail();
    Livewire::actingAs($coordinator)
        ->test(EditEmployee::class, ['record' => $org['empA']->getKey()])
        ->fillForm([
            'full_name' => 'First Person',
            'mobile' => '9123456710',
        ])
        ->call('save')
        ->assertHasFormErrors(['mobile']);

    expect($org['empA']->fresh()->mobile)->toBe('9000000001')
        ->and($employee->fresh()->user?->login_id)->toBe('9123456710');
});

it('reports existing duplicate accounts and does not merge or delete them', function () {
    $org = seedOrg();
    $hadUnique = collect(Schema::getIndexes('employees'))->contains(
        fn (array $index): bool => ($index['unique'] ?? false) === true && in_array('mobile', $index['columns'] ?? [], true),
    );
    if ($hadUnique) {
        Schema::table('employees', function ($table): void {
            $table->dropUnique(['mobile']);
        });
    }

    try {
        $first = Employee::query()->create([
            'center_id' => $org['centerA']->id,
            'full_name' => 'Duplicate Person',
            'mobile' => '9123456711',
            'status' => true,
        ]);
        User::query()->create([
            'employee_id' => $first->id,
            'name' => 'Duplicate Person',
            'email' => 'dup-a@test.local',
            'login_id' => '9123456711',
            'password' => Hash::make('6711'),
            'role' => UserRole::Employee->value,
            'is_active' => true,
        ]);
        $second = Employee::query()->create([
            'center_id' => $org['centerB']->id,
            'full_name' => 'duplicate person',
            'mobile' => '9123456711',
            'status' => true,
        ]);

        $before = User::query()->count();
        $groups = app(DuplicateUserReport::class)->groups();
        $match = collect($groups)->first(fn (array $group): bool => $group['match'] === 'name_mobile' && str_contains($group['key'], '9123456711'));

        expect($match)->not->toBeNull()
            ->and($match['resolution'][0])->toBe('Do not merge or delete these accounts automatically.')
            ->and(User::query()->count())->toBe($before)
            ->and($first->fresh())->not->toBeNull()
            ->and($second->fresh())->not->toBeNull();

        expect(fn () => UserIdentity::assertAvailable('Duplicate Person', '9123456711', allowEmployeeReuse: true))
            ->toThrow(ValidationException::class);
    } finally {
        User::query()->where('login_id', '9123456711')->delete();
        Employee::withTrashed()->where('mobile', '9123456711')->get()->each->forceDelete();
        if ($hadUnique) {
            Schema::table('employees', function ($table): void {
                $table->unique('mobile');
            });
        }
    }
});
