<?php

use App\Enums\UserRole;
use App\Filament\Resources\ExpenseClaims\Pages\CreateExpenseClaim;
use App\Filament\Resources\ExpenseClaims\Pages\ListExpenseClaims;
use App\Models\AppNotification;
use App\Models\ExpenseApproval;
use App\Models\ExpenseClaim;
use App\Models\User;
use App\Services\AdminCenterContext;
use App\Services\Notifications\ExpensePushNotifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

function expenseCoordinator(array $org, string $login, string $name = 'Coordinator'): User
{
    $coordinator = User::query()->create([
        'name' => $name,
        'email' => $login.'@test.local',
        'login_id' => $login,
        'password' => Hash::make('Coordinator@123'),
        'role' => UserRole::CenterCoordinator->value,
        'is_active' => true,
    ]);
    $coordinator->coordinatedCenters()->attach($org['centerA']->id);

    return $coordinator;
}

function expensePayload(bool $submit = true): array
{
    return [
        'action' => $submit ? 'submit' : 'draft',
        'items' => json_encode([[
            'expense_date' => '2026-11-20',
            'type' => 'food',
            'amount' => 250,
            'description' => 'Field lunch',
            'travel_from' => 'Center',
            'travel_to' => 'Village',
            'travel_km' => 12,
        ]]),
        'receipts' => [
            UploadedFile::fake()->image('receipt.jpg'),
        ],
    ];
}

it('walks an expense from the mobilizer through payment without skipping a level', function () {
    $org = seedOrg();
    $org['centerA']->update(['primary_center_manager_id' => $org['centerManager']->id]);
    $first = expenseCoordinator($org, 'coord-one', 'First Coordinator');
    $second = expenseCoordinator($org, 'coord-two', 'Second Coordinator');

    $created = $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', expensePayload())
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending_center_manager')
        ->assertJsonPath('data.total_amount', '250.00');

    $id = $created->json('data.id');

    expect(AppNotification::query()->where('user_id', $org['centerManager']->id)->count())->toBe(1)
        ->and(AppNotification::query()->where('user_id', $org['userA']->id)->where('type', 'expense_submitted')->count())->toBe(1);

    app(ExpensePushNotifier::class)->notifyUser(
        $org['centerManager'],
        ExpenseClaim::query()->findOrFail($id),
        'expense_pending_level_1',
        'Expense awaiting your approval',
        'again',
        '/manager/expenses/'.$id,
    );
    expect(AppNotification::query()->where('user_id', $org['centerManager']->id)->count())->toBe(1);

    $this->actingAs($org['userA'], 'sanctum')
        ->postJson('/api/manager/expenses/'.$id.'/approve')
        ->assertForbidden();

    $this->actingAs($org['centerManager'], 'sanctum')
        ->postJson('/api/manager/expenses/'.$id.'/approve', ['remarks' => 'Checked'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_center_coordinator');

    expect((int) $first->id)->toBeLessThan((int) $second->id);
    $this->actingAs($second, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve')
        ->assertForbidden();

    $this->actingAs($first, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve', ['remarks' => 'Center ok'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_final');

    $this->actingAs($org['director'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.payment_status', 'unpaid')
        ->assertJsonPath('data.status_label', 'Approved / Pending Payment');

    $this->actingAs($org['centerManager'], 'sanctum')
        ->postJson('/api/manager/expenses/'.$id.'/approve')
        ->assertForbidden();

    $this->actingAs($org['director'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/pay', [
            'amount' => 250,
            'payment_mode' => 'upi',
            'reference_number' => 'UPI1',
            'paid_on' => '2026-11-23',
        ])
        ->assertForbidden();

    $this->actingAs($org['admin'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/pay', [
            'amount' => 100,
            'payment_mode' => 'upi',
            'paid_on' => '2026-11-23',
        ])
        ->assertStatus(422);

    $this->actingAs($org['admin'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/pay', [
            'amount' => 250,
            'payment_mode' => 'upi',
            'reference_number' => 'UPI1',
            'paid_on' => '2026-11-23',
            'remarks' => 'Transferred',
        ])
        ->assertOk()
        ->assertJsonPath('data.payment_status', 'paid');

    $this->actingAs($org['userA'], 'sanctum')
        ->getJson('/api/expenses')
        ->assertOk()
        ->assertJsonPath('data.summary.paid', '250.00')
        ->assertJsonPath('data.summary.total_submitted', '250.00');

    expect(AppNotification::query()->where('user_id', $org['userA']->id)->where('type', 'expense_paid')->count())->toBe(1)
        ->and(ExpenseApproval::query()->where('expense_claim_id', $id)->count())->toBe(3);
});

it('stops the workflow when a claim is rejected', function () {
    $org = seedOrg();
    $coordinator = expenseCoordinator($org, 'coord-reject');
    $id = $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', expensePayload())
        ->json('data.id');

    $this->actingAs($org['centerManager'], 'sanctum')
        ->postJson('/api/manager/expenses/'.$id.'/reject', [])
        ->assertStatus(422);

    $this->actingAs($org['centerManager'], 'sanctum')
        ->postJson('/api/manager/expenses/'.$id.'/reject', ['remarks' => 'Bill is unclear'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    $this->actingAs($coordinator, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve')
        ->assertForbidden();

    expect(AppNotification::query()->where('user_id', $org['userA']->id)->where('type', 'expense_rejected')->value('body'))
        ->toContain('Bill is unclear');
});

it('flags a claim for admin when the next approver is missing', function () {
    $org = seedOrg();
    $id = $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', expensePayload())
        ->json('data.id');

    $this->actingAs($org['centerManager'], 'sanctum')
        ->postJson('/api/manager/expenses/'.$id.'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', 'needs_resolution');

    $this->actingAs($org['director'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve')
        ->assertForbidden();

    $this->actingAs($org['admin'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve', ['remarks' => 'No coordinator assigned'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_final');

    expect(AppNotification::query()->where('user_id', $org['admin']->id)->where('type', 'expense_needs_resolution_2')->count())->toBe(1);
});

it('does not let another center approve or view the claim', function () {
    $org = seedOrg();
    $other = User::query()->create([
        'name' => 'Other Manager',
        'email' => 'other-expense@test.local',
        'login_id' => 'otherexpense',
        'password' => Hash::make('CenterMgr@123'),
        'role' => UserRole::CenterManager->value,
        'is_active' => true,
    ]);
    $other->managedCenters()->attach($org['centerB']->id);

    $id = $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', expensePayload())
        ->json('data.id');

    $this->actingAs($other, 'sanctum')
        ->getJson('/api/manager/expenses/'.$id)
        ->assertForbidden();
    $this->actingAs($other, 'sanctum')
        ->postJson('/api/manager/expenses/'.$id.'/approve')
        ->assertForbidden();
});

it('keeps a draft until a receipt is attached', function () {
    $org = seedOrg();

    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', [
            'action' => 'draft',
            'items' => json_encode([[
                'expense_date' => '2026-11-20',
                'type' => 'hotel',
                'amount' => 800,
                'description' => 'Stay',
            ]]),
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft');

    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', [
            'action' => 'submit',
            'items' => json_encode([[
                'expense_date' => '2026-11-20',
                'type' => 'hotel',
                'amount' => 800,
                'description' => 'Stay',
            ]]),
        ])
        ->assertStatus(422);
});

it('routes a center manager expense to the coordinator and never lets them approve it', function () {
    $org = seedOrg();
    $coordinator = expenseCoordinator($org, 'coord-manager');
    $other = expenseCoordinator($org, 'coord-other-center');
    $other->coordinatedCenters()->detach();
    $other->coordinatedCenters()->attach($org['centerB']->id);

    $created = $this->actingAs($org['centerManager'], 'sanctum')
        ->post('/api/expenses', expensePayload())
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending_center_coordinator')
        ->assertJsonPath('data.employee_name', $org['centerManager']->name);

    $id = $created->json('data.id');

    expect(ExpenseClaim::query()->findOrFail($id)->employee_id)->toBeNull()
        ->and(AppNotification::query()->where('user_id', $coordinator->id)->where('type', 'expense_pending_level_2')->count())->toBe(1)
        ->and(AppNotification::query()->where('user_id', $org['centerManager']->id)->where('type', 'expense_submitted')->count())->toBe(1);

    $this->actingAs($org['centerManager'], 'sanctum')
        ->postJson('/api/manager/expenses/'.$id.'/approve')
        ->assertForbidden();

    $this->actingAs($other, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve')
        ->assertForbidden();

    $this->actingAs($coordinator, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve', ['remarks' => 'Manager claim ok'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_final');

    expect(AppNotification::query()->where('user_id', $org['director']->id)->where('type', 'expense_pending_final')->count())->toBe(1)
        ->and(AppNotification::query()->where('user_id', $org['centerManager']->id)->where('type', 'expense_level_approved')->count())->toBe(1);

    $this->actingAs($org['director'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $this->actingAs($org['admin'], 'sanctum')
        ->getJson('/api/director/expenses?status=paid')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($org['admin'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/pay', [
            'amount' => 250,
            'payment_mode' => 'cash',
            'paid_on' => '2026-11-23',
        ])
        ->assertOk()
        ->assertJsonPath('data.payment_status', 'paid');

    expect(AppNotification::query()->where('user_id', $org['centerManager']->id)->where('type', 'expense_paid')->count())->toBe(1);

    $this->app['auth']->forgetGuards();

    Livewire::actingAs($org['admin'])
        ->test(ListExpenseClaims::class)
        ->assertSee('Pending Approval');

    $this->withSession([AdminCenterContext::SESSION_KEY => $org['centerA']->id]);
    Livewire::actingAs($org['centerManager'])
        ->test(CreateExpenseClaim::class)
        ->assertSee('Save Draft')
        ->assertSee('Submit');
});
