<?php

use App\Enums\CenterExpenseCategory;
use App\Enums\ExpenseClaimStatus;
use App\Enums\ExpenseType;
use App\Enums\UserRole;
use App\Filament\Resources\ExpenseClaims\Pages\CreateExpenseClaim;
use App\Models\User;
use App\Services\AdminCenterContext;
use App\Services\Expenses\ExpenseClaimService;
use App\Support\AttendanceCalendar;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('lets a center manager submit a center category without travel fields and still accepts mobilizer food and travelling', function () {
    $org = seedOrg();
    $coordinator = User::query()->create([
        'name' => 'Category Coordinator',
        'email' => 'coord-center-cat@test.local',
        'login_id' => 'coordcentercat',
        'password' => Hash::make('Coordinator@123'),
        'role' => UserRole::CenterCoordinator->value,
        'is_active' => true,
    ]);
    $coordinator->coordinatedCenters()->attach($org['centerA']->id);

    $date = AttendanceCalendar::today()->toDateString();

    expect(array_keys(ExpenseType::options()))->toBe(['travelling', 'hotel', 'food', 'other'])
        ->and(CenterExpenseCategory::options())->toHaveCount(20)
        ->and(CenterExpenseCategory::options()['building_rent'])->toBe('Building Rent');

    $this->withSession([AdminCenterContext::SESSION_KEY => $org['centerA']->id]);
    Livewire::actingAs($org['centerManager'])
        ->test(CreateExpenseClaim::class)
        ->assertSee('Category')
        ->assertSee('Building Rent')
        ->assertSee('Miscellaneous Expenses')
        ->assertSee('Add Another Expense')
        ->assertSee('Bill Upload')
        ->assertSee('Save Draft')
        ->assertSee('Submit')
        ->assertDontSee('Travel from')
        ->assertDontSee('Travel to')
        ->assertDontSee('Travel km');

    $claim = app(ExpenseClaimService::class)->save(
        $org['centerManager'],
        [[
            'expense_date' => $date,
            'type' => 'building_rent',
            'amount' => 1500,
            'description' => 'November rent',
        ]],
        [],
        true,
    );

    $item = $claim->items->first();
    $presented = app(ExpenseClaimService::class)->present($claim);

    expect($claim->status)->toBe(ExpenseClaimStatus::PendingCenterCoordinator)
        ->and($item->getRawOriginal('type'))->toBe('building_rent')
        ->and($item->type)->toBe(CenterExpenseCategory::BuildingRent)
        ->and($item->type->label())->toBe('Building Rent')
        ->and($presented['items'][0]['type'])->toBe('building_rent')
        ->and($presented['items'][0]['type_label'])->toBe('Building Rent')
        ->and($presented['items'][0]['amount'])->toBe('1500.00')
        ->and($item->travel_from)->toBeNull()
        ->and($item->travel_to)->toBeNull()
        ->and($item->travel_km)->toBeNull()
        ->and($item->travel_mode)->toBeNull()
        ->and($item->recorded_km)->toBeNull()
        ->and($item->eligible_km)->toBeNull();

    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', [
            'action' => 'submit',
            'items' => json_encode([[
                'expense_date' => $date,
                'type' => 'building_rent',
                'amount' => 100,
                'description' => 'Rent',
            ]]),
        ])
        ->assertStatus(422);

    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', [
            'action' => 'submit',
            'items' => json_encode([[
                'expense_date' => $date,
                'type' => 'food',
                'amount' => 250,
                'description' => 'Field lunch',
                'travel_from' => 'Center',
                'travel_to' => 'Village',
                'travel_km' => 12,
            ]]),
        ])
        ->assertCreated()
        ->assertJsonPath('data.items.0.type', 'food')
        ->assertJsonPath('data.items.0.type_label', 'Food')
        ->assertJsonPath('data.status', 'pending_center_manager');

    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', [
            'action' => 'submit',
            'items' => json_encode([[
                'expense_date' => $date,
                'type' => 'travelling',
                'travel_mode' => 'public',
                'travel_from' => 'Center',
                'travel_to' => 'Village',
                'amount' => 500,
                'description' => 'Village visit',
            ]]),
        ])
        ->assertCreated()
        ->assertJsonPath('data.items.0.type', 'travelling')
        ->assertJsonPath('data.items.0.type_label', 'Travelling')
        ->assertJsonPath('data.total_amount', '500.00')
        ->assertJsonPath('data.status', 'pending_center_manager');
});
