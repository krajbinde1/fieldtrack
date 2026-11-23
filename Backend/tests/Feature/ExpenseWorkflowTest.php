<?php

use App\Enums\ExpenseClaimStatus;
use App\Enums\UserRole;
use App\Filament\Resources\ExpenseClaims\Pages\CreateExpenseClaim;
use App\Filament\Resources\ExpenseClaims\Pages\ListExpenseClaims;
use App\Filament\Resources\ExpenseClaims\Pages\ViewExpenseClaim;
use App\Models\AppNotification;
use App\Models\ExpenseApproval;
use App\Models\ExpenseClaim;
use App\Models\User;
use App\Services\AdminCenterContext;
use App\Services\Expenses\ExpenseClaimService;
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
    $this->actingAs($first, 'sanctum')
        ->getJson('/api/director/expenses?status=pending')
        ->assertOk()
        ->assertJsonFragment(['id' => $id, 'total_amount' => '250.00']);
    $this->actingAs($second, 'sanctum')
        ->getJson('/api/director/expenses?status=pending')
        ->assertOk()
        ->assertJsonFragment(['id' => $id, 'total_amount' => '250.00']);

    expect(AppNotification::query()->where('user_id', $first->id)->where('type', 'expense_pending_level_2')->count())->toBe(1)
        ->and(AppNotification::query()->where('user_id', $second->id)->where('type', 'expense_pending_level_2')->count())->toBe(1);

    $this->actingAs($first, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve', ['remarks' => 'Center ok'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_director')
        ->assertJsonPath('data.pending_approval', 'Director — waiting on Director');

    $this->actingAs($second, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve')
        ->assertForbidden();

    $this->actingAs($org['admin'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve')
        ->assertForbidden();

    $this->actingAs($org['director'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_final')
        ->assertJsonPath('data.pending_approval', 'Admin — waiting on Admin');

    $this->actingAs($org['director'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve')
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
        ->postJson('/api/director/expenses/'.$id.'/approve', ['remarks' => 'Final ok'])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.payment_status', 'unpaid')
        ->assertJsonPath('data.status_label', 'Approved / Pending Payment');

    $this->actingAs($org['centerManager'], 'sanctum')
        ->postJson('/api/manager/expenses/'.$id.'/approve')
        ->assertForbidden();

    $this->actingAs($org['centerManager'], 'sanctum')
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
        ->and(ExpenseApproval::query()->where('expense_claim_id', $id)->count())->toBe(4)
        ->and(AppNotification::query()->where('user_id', $org['admin']->id)->where('type', 'expense_pending_level_3')->count())->toBe(0)
        ->and(AppNotification::query()->where('user_id', $org['director']->id)->where('type', 'expense_pending_level_3')->count())->toBe(1)
        ->and(AppNotification::query()->where('user_id', $org['admin']->id)->where('type', 'expense_pending_final')->count())->toBe(1);
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

it('keeps a claim waiting when the coordinator is missing and does not let admin approve it', function () {
    $org = seedOrg();
    $id = $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', expensePayload())
        ->json('data.id');

    $this->actingAs($org['centerManager'], 'sanctum')
        ->postJson('/api/manager/expenses/'.$id.'/approve', ['remarks' => 'Checked'])
        ->assertOk()
        ->assertJsonPath('data.status', 'needs_resolution')
        ->assertJsonPath('data.pending_approval', 'Center Coordinator — no approver is assigned');

    $claim = ExpenseClaim::query()->findOrFail($id);
    $service = app(ExpenseClaimService::class);

    $this->actingAs($org['director'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve')
        ->assertForbidden();

    $this->actingAs($org['admin'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve', ['remarks' => 'No coordinator assigned'])
        ->assertForbidden();

    expect($claim->fresh()->status)->toBe(ExpenseClaimStatus::NeedsResolution)
        ->and($claim->fresh()->blocked_level)->toBe(2)
        ->and($service->queueQuery($org['admin'])->whereKey($id)->exists())->toBeFalse()
        ->and($service->queueQuery($org['director'])->whereKey($id)->exists())->toBeFalse()
        ->and($service->canDecide($org['admin'], $claim->fresh()))->toBeFalse()
        ->and($service->approvalHistoryLines($claim->fresh())[0])->toContain('Level 1 approved by CM (Center Manager)')
        ->and($service->approvalHistoryLines($claim->fresh())[0])->toContain('Checked')
        ->and(AppNotification::query()->where('user_id', $org['admin']->id)->where('type', 'expense_needs_resolution_2')->count())->toBe(1);

    $this->actingAs($org['admin'], 'sanctum')
        ->getJson('/api/director/expenses?status=pending')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($org['admin'], 'sanctum')
        ->getJson('/api/director/expenses/'.$id)
        ->assertOk()
        ->assertJsonPath('data.pending_approval', 'Center Coordinator — no approver is assigned')
        ->assertJsonPath('data.status', 'needs_resolution');

    $this->app['auth']->forgetGuards();

    Livewire::actingAs($org['admin'])
        ->test(ListExpenseClaims::class)
        ->assertCanNotSeeTableRecords([$claim]);

    Livewire::actingAs($org['admin'])
        ->test(ListExpenseClaims::class)
        ->set('activeTab', 'history')
        ->assertCanNotSeeTableRecords([$claim]);

    $history = $service->approvalHistoryLines($claim->fresh())[0];
    expect($history)->toContain('Level 1 approved by CM (Center Manager)');
    Livewire::actingAs($org['admin'])
        ->test(ViewExpenseClaim::class, ['record' => $id])
        ->assertSee('Center Manager Approval')
        ->assertSee('CM')
        ->assertSee('Checked')
        ->assertSee('No approver is assigned');
});

it('lists a mobilizer claim for the assigned center manager before any center is opened', function () {
    $org = seedOrg();
    $org['centerManager']->managedCenters()->attach($org['centerB']->id);
    $coordinator = expenseCoordinator($org, 'coord-list', 'List Coordinator');

    $created = $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', expensePayload())
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending_center_manager')
        ->assertJsonPath('data.pending_approval', 'Center Manager — waiting on CM');

    $id = $created->json('data.id');
    $claim = ExpenseClaim::query()->findOrFail($id);
    $service = app(ExpenseClaimService::class);

    expect($service->queueQuery($org['admin'])->whereKey($id)->exists())->toBeFalse()
        ->and($service->queueQuery($org['director'])->whereKey($id)->exists())->toBeFalse()
        ->and($service->queueQuery($org['centerManager'])->whereKey($id)->exists())->toBeTrue();

    $this->app['auth']->forgetGuards();

    Livewire::actingAs($org['centerManager'])
        ->test(ListExpenseClaims::class)
        ->assertCanSeeTableRecords([$claim]);

    $this->withSession([AdminCenterContext::SESSION_KEY => $org['centerB']->id]);

    Livewire::actingAs($org['centerManager'])
        ->test(ListExpenseClaims::class)
        ->assertCanSeeTableRecords([$claim]);

    Livewire::actingAs($org['centerManager'])
        ->test(ListExpenseClaims::class)
        ->set('activeTab', 'history')
        ->assertCanNotSeeTableRecords([$claim]);

    $this->actingAs($org['centerManager'], 'sanctum')
        ->postJson('/api/manager/expenses/'.$id.'/approve', ['remarks' => 'Manager checked'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_center_coordinator')
        ->assertJsonPath('data.pending_approval', 'Center Coordinator — waiting on List Coordinator');

    expect($service->queueQuery($coordinator)->whereKey($id)->exists())->toBeTrue()
        ->and($service->queueQuery($org['centerManager'])->whereKey($id)->exists())->toBeFalse()
        ->and($service->queueQuery($org['admin'])->whereKey($id)->exists())->toBeFalse();

    $this->app['auth']->forgetGuards();
    $this->withSession([AdminCenterContext::SESSION_KEY => $org['centerB']->id]);
    $coordinator->coordinatedCenters()->attach($org['centerB']->id);

    Livewire::actingAs($coordinator)
        ->test(ListExpenseClaims::class)
        ->assertCanSeeTableRecords([$claim->fresh()]);

    $this->actingAs($coordinator, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve', ['remarks' => 'Coordinator checked'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_director')
        ->assertJsonPath('data.pending_approval', 'Director — waiting on Director');

    expect($service->queueQuery($org['admin'])->whereKey($id)->exists())->toBeFalse()
        ->and($service->queueQuery($org['director'])->whereKey($id)->exists())->toBeTrue()
        ->and($service->approvalHistoryLines($claim->fresh()))->toHaveCount(2);

    $this->app['auth']->forgetGuards();
    Livewire::actingAs($org['admin'])
        ->test(ListExpenseClaims::class)
        ->assertCanNotSeeTableRecords([$claim->fresh()]);

    Livewire::actingAs($org['director'])
        ->test(ViewExpenseClaim::class, ['record' => $id])
        ->assertSee('Center Manager Approval')
        ->assertSee('CM')
        ->assertSee('Manager checked')
        ->assertSee('List Coordinator')
        ->assertSee('Coordinator checked')
        ->assertSee('Director Approval')
        ->assertSee('waiting on Director')
        ->assertDontSee('waiting on Admin');
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

it('submits an expense when the bill photo is left off', function () {
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
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending_center_manager')
        ->assertJsonPath('data.items.0.has_receipt', false);
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
        ->assertJsonPath('data.status', 'pending_director');

    expect(AppNotification::query()->where('user_id', $org['director']->id)->where('type', 'expense_pending_level_3')->count())->toBe(1)
        ->and(AppNotification::query()->where('user_id', $org['admin']->id)->where('type', 'expense_pending_final')->count())->toBe(0)
        ->and(AppNotification::query()->where('user_id', $org['centerManager']->id)->where('type', 'expense_level_approved')->count())->toBe(1);

    $this->actingAs($org['admin'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve')
        ->assertForbidden();

    $this->actingAs($org['director'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_final');

    $this->actingAs($org['admin'], 'sanctum')
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

it('moves a 500 rupee travelling expense through every approver and then payment', function () {
    $org = seedOrg();
    $org['centerA']->update(['primary_center_manager_id' => $org['centerManager']->id]);
    $first = expenseCoordinator($org, 'coord-500-a', 'Coordinator A');
    $second = expenseCoordinator($org, 'coord-500-b', 'Coordinator B');
    $outsider = expenseCoordinator($org, 'coord-500-out', 'Outside Coordinator');
    $outsider->coordinatedCenters()->detach();
    $outsider->coordinatedCenters()->attach($org['centerB']->id);

    $created = $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', [
            'action' => 'submit',
            'items' => json_encode([[
                'expense_date' => '2026-11-20',
                'type' => 'travelling',
                'travel_mode' => 'public',
                'travel_from' => 'Center',
                'travel_to' => 'Village',
                'amount' => 500,
                'description' => 'Village visit',
            ]]),
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending_center_manager')
        ->assertJsonPath('data.total_amount', '500.00')
        ->assertJsonPath('data.employee_name', 'Emp A')
        ->assertJsonPath('data.center_name', 'Center A')
        ->assertJsonPath('data.items.0.type', 'travelling')
        ->assertJsonPath('data.items.0.has_receipt', false);

    $id = $created->json('data.id');

    $managerNotice = AppNotification::query()
        ->where('user_id', $org['centerManager']->id)
        ->where('type', 'expense_pending_level_1')
        ->first();
    expect($managerNotice)->not->toBeNull()
        ->and($managerNotice->data['route'])->toBe('/manager/expenses/'.$id)
        ->and($managerNotice->data['expense_claim_id'])->toBe((string) $id);

    $this->actingAs($org['centerManager'], 'sanctum')
        ->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonPath('data.pending_expenses', 1);
    $this->actingAs($org['centerManager'], 'sanctum')
        ->getJson('/api/manager/expenses?status=pending')
        ->assertOk()
        ->assertJsonPath('data.0.id', $id)
        ->assertJsonPath('data.0.total_amount', '500.00')
        ->assertJsonPath('data.0.employee_name', 'Emp A');

    $this->actingAs($org['centerManager'], 'sanctum')
        ->postJson('/api/manager/expenses/'.$id.'/approve', ['remarks' => 'Travel checked'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_center_coordinator');

    $this->actingAs($org['centerManager'], 'sanctum')
        ->getJson('/api/dashboard')
        ->assertJsonPath('data.pending_expenses', 0);
    $this->actingAs($first, 'sanctum')
        ->getJson('/api/dashboard')
        ->assertJsonPath('data.pending_expenses', 1);
    $this->actingAs($second, 'sanctum')
        ->getJson('/api/director/expenses?status=pending')
        ->assertOk()
        ->assertJsonFragment(['id' => $id, 'total_amount' => '500.00']);
    $this->actingAs($outsider, 'sanctum')
        ->getJson('/api/director/expenses?status=pending')
        ->assertOk()
        ->assertJsonMissing(['id' => $id]);

    $coordinatorNotice = AppNotification::query()->where('user_id', $second->id)->where('type', 'expense_pending_level_2')->first();
    expect(AppNotification::query()->where('user_id', $first->id)->where('type', 'expense_pending_level_2')->count())->toBe(1)
        ->and(AppNotification::query()->where('user_id', $second->id)->where('type', 'expense_pending_level_2')->count())->toBe(1)
        ->and($coordinatorNotice->data['route'])->toBe('/director/expenses/'.$id)
        ->and($coordinatorNotice->data['expense_claim_id'])->toBe((string) $id);

    $this->actingAs($outsider, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve')
        ->assertForbidden();
    $this->actingAs($second, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve', ['remarks' => 'Coordinator checked'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_director');
    $this->actingAs($first, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve')
        ->assertForbidden();

    $this->actingAs($org['admin'], 'sanctum')
        ->getJson('/api/dashboard')
        ->assertJsonPath('data.pending_expenses', 0);
    $this->actingAs($org['admin'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve')
        ->assertForbidden();
    $this->actingAs($org['director'], 'sanctum')
        ->getJson('/api/director/expenses?status=pending')
        ->assertOk()
        ->assertJsonFragment(['id' => $id, 'total_amount' => '500.00']);

    $this->actingAs($org['director'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve', ['remarks' => 'Director ok'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_final');
    $this->actingAs($org['director'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/pay', [
            'amount' => 500,
            'payment_mode' => 'upi',
            'paid_on' => '2026-11-23',
        ])
        ->assertForbidden();

    $this->actingAs($org['admin'], 'sanctum')
        ->getJson('/api/dashboard')
        ->assertJsonPath('data.pending_expenses', 1);
    $this->actingAs($org['admin'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve', ['remarks' => 'Final ok'])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.status_label', 'Approved / Pending Payment');

    $this->actingAs($org['userA'], 'sanctum')
        ->getJson('/api/expenses')
        ->assertOk()
        ->assertJsonPath('data.claims.0.status', 'approved')
        ->assertJsonPath('data.claims.0.payment_status', 'unpaid');

    $approvedNotice = AppNotification::query()->where('user_id', $org['userA']->id)->where('type', 'expense_approved')->first();
    expect($approvedNotice->data['route'])->toBe('/expenses/'.$id)
        ->and($approvedNotice->data['expense_claim_id'])->toBe((string) $id);

    $this->actingAs($first, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/pay', [
            'amount' => 500,
            'payment_mode' => 'cash',
            'paid_on' => '2026-11-23',
        ])
        ->assertForbidden();

    $this->actingAs($org['admin'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/pay', [
            'amount' => 500,
            'payment_mode' => 'upi',
            'reference_number' => 'UPI500',
            'paid_on' => '2026-11-23',
            'remarks' => 'Paid to mobilizer',
        ])
        ->assertOk()
        ->assertJsonPath('data.payment_status', 'paid');

    $this->actingAs($org['userA'], 'sanctum')
        ->getJson('/api/expenses')
        ->assertJsonPath('data.claims.0.payment_status', 'paid')
        ->assertJsonPath('data.summary.paid', '500.00');

    expect(AppNotification::query()->where('user_id', $org['userA']->id)->where('type', 'expense_paid')->count())->toBe(1)
        ->and(ExpenseApproval::query()->where('expense_claim_id', $id)->count())->toBe(4);

    $this->app['auth']->forgetGuards();
    Livewire::actingAs($org['admin'])
        ->test(ListExpenseClaims::class)
        ->set('activeTab', 'paid')
        ->assertCanSeeTableRecords([ExpenseClaim::query()->findOrFail($id)]);
});

it('rejects an expense at the coordinator and final stages', function () {
    $org = seedOrg();
    $coordinator = expenseCoordinator($org, 'coord-stage-reject');

    $coordinatorClaim = $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', expensePayload())
        ->json('data.id');
    $this->actingAs($org['centerManager'], 'sanctum')
        ->postJson('/api/manager/expenses/'.$coordinatorClaim.'/approve')
        ->assertOk();
    $this->actingAs($coordinator, 'sanctum')
        ->postJson('/api/director/expenses/'.$coordinatorClaim.'/reject', ['remarks' => 'Not this center'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');
    $this->actingAs($org['admin'], 'sanctum')
        ->postJson('/api/director/expenses/'.$coordinatorClaim.'/approve')
        ->assertForbidden();
    expect(AppNotification::query()->where('user_id', $org['userA']->id)->where('type', 'expense_rejected')->value('body'))
        ->toContain('Not this center');

    $finalClaim = $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', expensePayload())
        ->json('data.id');
    $this->actingAs($org['centerManager'], 'sanctum')
        ->postJson('/api/manager/expenses/'.$finalClaim.'/approve')
        ->assertOk();
    $this->actingAs($coordinator, 'sanctum')
        ->postJson('/api/director/expenses/'.$finalClaim.'/approve')
        ->assertOk();
    $this->actingAs($org['director'], 'sanctum')
        ->postJson('/api/director/expenses/'.$finalClaim.'/reject', ['remarks' => 'Amount is too high'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');
    expect(AppNotification::query()->where('user_id', $org['userA']->id)->where('type', 'expense_rejected')->where('data->expense_claim_id', (string) $finalClaim)->value('body'))
        ->toContain('Amount is too high');
});

it('lets only an admin record payment after the director and admin have approved', function () {
    $org = seedOrg();
    $coordinator = expenseCoordinator($org, 'coord-director-pay');
    $id = $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', expensePayload())
        ->json('data.id');

    $this->actingAs($org['centerManager'], 'sanctum')
        ->postJson('/api/manager/expenses/'.$id.'/approve')
        ->assertOk();
    $this->actingAs($coordinator, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve')
        ->assertOk();
    $this->actingAs($org['director'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_final');
    $this->actingAs($org['director'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/pay', [
            'amount' => 250,
            'payment_mode' => 'cash',
            'paid_on' => '2026-11-23',
        ])
        ->assertForbidden();
    $this->actingAs($org['admin'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve')
        ->assertOk()
        ->assertJsonPath('data.status_label', 'Approved / Pending Payment');
    $this->actingAs($org['director'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/pay', [
            'amount' => 250,
            'payment_mode' => 'cash',
            'paid_on' => '2026-11-23',
        ])
        ->assertForbidden();
    $this->actingAs($org['admin'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/pay', [
            'amount' => 250,
            'payment_mode' => 'cash',
            'paid_on' => '2026-11-23',
        ])
        ->assertOk()
        ->assertJsonPath('data.payment_status', 'paid');

    expect(AppNotification::query()->where('user_id', $org['userA']->id)->where('type', 'expense_paid')->count())->toBe(1);
});

it('shows a mobilizer claim to the assigned center manager when another center is selected', function () {
    $org = seedOrg();
    $org['centerManager']->managedCenters()->attach($org['centerB']->id);
    $coordinator = expenseCoordinator($org, 'coord-visible', 'Visible Coordinator');

    $id = $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', expensePayload())
        ->assertCreated()
        ->json('data.id');
    $claim = ExpenseClaim::query()->findOrFail($id);
    $service = app(ExpenseClaimService::class);

    expect($claim->center_id)->toBe($org['centerA']->id)
        ->and($claim->employee_id)->toBe($org['empA']->id)
        ->and($claim->status)->toBe(ExpenseClaimStatus::PendingCenterManager)
        ->and($service->queueQuery($org['centerManager'])->whereKey($id)->exists())->toBeTrue()
        ->and($service->queueQuery($org['admin'])->whereKey($id)->exists())->toBeFalse();

    $this->actingAs($org['centerManager'], 'sanctum')
        ->getJson('/api/manager/expenses?status=pending&center_id='.$org['centerB']->id)
        ->assertOk()
        ->assertJsonFragment(['id' => $id]);

    $this->app['auth']->forgetGuards();
    $this->withSession([AdminCenterContext::SESSION_KEY => $org['centerB']->id]);
    Livewire::actingAs($org['centerManager'])
        ->test(ListExpenseClaims::class)
        ->assertCanSeeTableRecords([$claim]);

    $claim->update(['center_id' => $org['centerB']->id]);
    $org['centerManager']->managedCenters()->detach($org['centerB']->id);

    expect($service->queueQuery($org['centerManager']->fresh())->whereKey($id)->exists())->toBeTrue()
        ->and($service->queueQuery($org['admin'])->whereKey($id)->exists())->toBeFalse();

    $this->actingAs($org['centerManager'], 'sanctum')
        ->getJson('/api/manager/expenses?status=pending&center_id='.$org['centerB']->id)
        ->assertOk()
        ->assertJsonFragment(['id' => $id]);

    $this->app['auth']->forgetGuards();
    Livewire::actingAs($org['centerManager']->fresh())
        ->test(ListExpenseClaims::class)
        ->assertCanSeeTableRecords([$claim->fresh()]);

    $this->actingAs($org['centerManager'], 'sanctum')
        ->postJson('/api/manager/expenses/'.$id.'/approve', ['remarks' => 'Assigned center'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_center_coordinator');

    expect($claim->fresh()->center_id)->toBe($org['centerA']->id)
        ->and($service->queueQuery($coordinator)->whereKey($id)->exists())->toBeTrue()
        ->and($service->queueQuery($org['centerManager']->fresh())->whereKey($id)->exists())->toBeFalse()
        ->and($service->queueQuery($org['admin'])->whereKey($id)->exists())->toBeFalse()
        ->and($service->queueQuery($org['director'])->whereKey($id)->exists())->toBeFalse();
});

it('shows a mobilizer claim to the primary center manager without a pivot row', function () {
    $org = seedOrg();
    $org['centerManager']->managedCenters()->detach();
    $org['centerA']->update(['primary_center_manager_id' => $org['centerManager']->id]);

    $id = $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', expensePayload())
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending_center_manager')
        ->json('data.id');

    $service = app(ExpenseClaimService::class);
    $claim = ExpenseClaim::query()->findOrFail($id);

    expect($service->queueQuery($org['centerManager']->fresh())->whereKey($id)->exists())->toBeTrue()
        ->and($service->queueQuery($org['admin'])->whereKey($id)->exists())->toBeFalse()
        ->and($claim->current_level)->toBe(1);

    $this->app['auth']->forgetGuards();
    Livewire::actingAs($org['centerManager']->fresh())
        ->test(ListExpenseClaims::class)
        ->assertCanSeeTableRecords([$claim]);
});

it('closes each stage after one approval and keeps pending-with on that stage', function () {
    $org = seedOrg();
    $peer = User::query()->create([
        'name' => 'Peer Manager',
        'email' => 'peer-manager@test.local',
        'login_id' => 'peermanager',
        'password' => Hash::make('CenterMgr@123'),
        'role' => UserRole::CenterManager->value,
        'is_active' => true,
    ]);
    $org['centerA']->update(['primary_center_manager_id' => $peer->id]);
    $first = expenseCoordinator($org, 'stage-coord-a', 'First Coordinator');
    $second = expenseCoordinator($org, 'stage-coord-b', 'Second Coordinator');
    $service = app(ExpenseClaimService::class);

    $id = (int) $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', expensePayload())
        ->assertCreated()
        ->json('data.id');

    expect($service->queueQuery($org['centerManager'])->whereKey($id)->exists())->toBeTrue()
        ->and($service->queueQuery($peer)->whereKey($id)->exists())->toBeTrue()
        ->and($service->queueQuery($first)->whereKey($id)->exists())->toBeFalse()
        ->and($service->queueQuery($org['director'])->whereKey($id)->exists())->toBeFalse()
        ->and($service->queueQuery($org['admin'])->whereKey($id)->exists())->toBeFalse();

    $this->actingAs($org['centerManager'], 'sanctum')
        ->postJson('/api/manager/expenses/'.$id.'/approve', ['remarks' => 'One manager is enough'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_center_coordinator')
        ->assertJsonPath('data.pending_with', 'Center Coordinator — waiting on First Coordinator, Second Coordinator');

    $managerApprovalId = ExpenseApproval::query()->where('expense_claim_id', $id)->where('level', 1)->value('id');

    expect($service->queueQuery($org['centerManager'])->whereKey($id)->exists())->toBeFalse()
        ->and($service->queueQuery($peer)->whereKey($id)->exists())->toBeFalse()
        ->and($service->queueQuery($first)->whereKey($id)->exists())->toBeTrue()
        ->and($service->queueQuery($second)->whereKey($id)->exists())->toBeTrue()
        ->and($service->queueQuery($org['director'])->whereKey($id)->exists())->toBeFalse()
        ->and($service->queueQuery($org['admin'])->whereKey($id)->exists())->toBeFalse()
        ->and(AppNotification::query()->where('user_id', $peer->id)->where('type', 'expense_pending_level_2')->count())->toBe(0);

    $this->actingAs($peer, 'sanctum')
        ->postJson('/api/manager/expenses/'.$id.'/approve')
        ->assertForbidden();
    $this->actingAs($org['centerManager'], 'sanctum')
        ->postJson('/api/manager/expenses/'.$id.'/approve')
        ->assertForbidden();
    expect(ExpenseApproval::query()->where('expense_claim_id', $id)->count())->toBe(1)
        ->and(ExpenseApproval::query()->where('expense_claim_id', $id)->value('id'))->toBe($managerApprovalId);

    $this->actingAs($first, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve', ['remarks' => 'One coordinator is enough'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_director')
        ->assertJsonPath('data.pending_with', 'Director — waiting on Director')
        ->assertJsonPath('data.timeline.pending_text', 'Director Approval — waiting on Director');

    expect($service->queueQuery($second)->whereKey($id)->exists())->toBeFalse()
        ->and($service->queueQuery($first)->whereKey($id)->exists())->toBeFalse()
        ->and($service->queueQuery($org['director'])->whereKey($id)->exists())->toBeTrue()
        ->and($service->queueQuery($org['admin'])->whereKey($id)->exists())->toBeFalse()
        ->and(AppNotification::query()->where('user_id', $org['admin']->id)->where('type', 'expense_pending_final')->count())->toBe(0)
        ->and(AppNotification::query()->where('user_id', $org['director']->id)->where('type', 'expense_pending_level_3')->count())->toBe(1);

    $this->actingAs($second, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve')
        ->assertForbidden();

    $this->actingAs($org['director'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve', ['remarks' => 'Director checked'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_final')
        ->assertJsonPath('data.pending_with', 'Admin — waiting on Admin');

    expect($service->queueQuery($org['director'])->whereKey($id)->exists())->toBeFalse()
        ->and($service->queueQuery($org['admin'])->whereKey($id)->exists())->toBeTrue();

    $this->actingAs($org['director'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/pay', [
            'amount' => 250,
            'payment_mode' => 'cash',
            'paid_on' => '2026-11-23',
        ])
        ->assertForbidden();
    $this->actingAs($org['director'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve')
        ->assertForbidden();

    $this->actingAs($org['admin'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve', ['remarks' => 'Admin final'])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.payment_status', 'unpaid')
        ->assertJsonPath('data.status_label', 'Approved / Pending Payment')
        ->assertJsonPath('data.pending_with', 'Payment');

    expect($service->queueQuery($org['admin'])->whereKey($id)->exists())->toBeFalse()
        ->and($service->queueQuery($org['director'])->whereKey($id)->exists())->toBeFalse();

    $this->actingAs($org['director'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/pay', [
            'amount' => 250,
            'payment_mode' => 'upi',
            'paid_on' => '2026-11-23',
        ])
        ->assertForbidden();
    $this->actingAs($first, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/pay', [
            'amount' => 250,
            'payment_mode' => 'upi',
            'paid_on' => '2026-11-23',
        ])
        ->assertForbidden();
    $this->actingAs($org['admin'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/pay', [
            'amount' => 250,
            'payment_mode' => 'upi',
            'paid_on' => '2026-11-23',
        ])
        ->assertOk()
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonPath('data.pending_with', null);

    expect(ExpenseApproval::query()->where('expense_claim_id', $id)->orderBy('level')->pluck('level')->all())->toBe([1, 2, 3, 4])
        ->and(ExpenseApproval::query()->find($managerApprovalId)?->approver_name)->toBe('CM');
});

it('advances a stuck manager stage from the stored approval without rewriting it', function () {
    $org = seedOrg();
    $peer = User::query()->create([
        'name' => 'Stuck Peer',
        'email' => 'stuck-peer@test.local',
        'login_id' => 'stuckpeer',
        'password' => Hash::make('CenterMgr@123'),
        'role' => UserRole::CenterManager->value,
        'is_active' => true,
    ]);
    $org['centerA']->update(['primary_center_manager_id' => $peer->id]);
    $coordinator = expenseCoordinator($org, 'stuck-coord', 'Stuck Coordinator');
    $service = app(ExpenseClaimService::class);

    $id = (int) $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', expensePayload())
        ->assertCreated()
        ->json('data.id');
    $this->actingAs($org['centerManager'], 'sanctum')
        ->postJson('/api/manager/expenses/'.$id.'/approve', ['remarks' => 'Already approved'])
        ->assertOk();

    $approval = ExpenseApproval::query()->where('expense_claim_id', $id)->firstOrFail();
    $original = $approval->only(['id', 'level', 'action', 'user_id', 'approver_name', 'role', 'remarks']);
    ExpenseClaim::query()->whereKey($id)->update([
        'status' => ExpenseClaimStatus::PendingCenterManager->value,
        'current_level' => 1,
    ]);

    $stuck = ExpenseClaim::query()->findOrFail($id);
    expect($service->queueQuery($peer)->whereKey($id)->exists())->toBeFalse()
        ->and($service->queueQuery($org['centerManager'])->whereKey($id)->exists())->toBeFalse()
        ->and($service->queueQuery($coordinator)->whereKey($id)->exists())->toBeTrue()
        ->and($service->queueQuery($org['director'])->whereKey($id)->exists())->toBeFalse()
        ->and($service->queueQuery($org['admin'])->whereKey($id)->exists())->toBeFalse()
        ->and($service->pendingWithText($stuck))->toBe('Center Coordinator — waiting on Stuck Coordinator')
        ->and($service->pendingWithText($stuck))->not->toContain('Stuck Peer');

    $synced = $service->syncOpenStage($stuck);
    $approval->refresh();

    expect($synced->status)->toBe(ExpenseClaimStatus::PendingCenterCoordinator)
        ->and($synced->current_level)->toBe(2)
        ->and(ExpenseApproval::query()->where('expense_claim_id', $id)->count())->toBe(1)
        ->and($approval->only(['id', 'level', 'action', 'user_id', 'approver_name', 'role', 'remarks']))->toBe($original);
});

it('leaves a completed level-3 final approval on the stored approver role', function () {
    $org = seedOrg();
    $service = app(ExpenseClaimService::class);
    $claim = ExpenseClaim::query()->create([
        'employee_id' => $org['empA']->id,
        'center_id' => $org['centerA']->id,
        'scheme_id' => $org['centerA']->scheme_id,
        'created_by_user_id' => $org['userA']->id,
        'claim_number' => 'EXP-LEGACY-ADMIN',
        'status' => ExpenseClaimStatus::Approved,
        'payment_status' => 'unpaid',
        'current_level' => 3,
        'total_amount' => 250,
        'submitted_at' => now(),
        'final_approved_at' => now(),
    ]);
    $row = ExpenseApproval::query()->create([
        'expense_claim_id' => $claim->id,
        'level' => 3,
        'action' => 'approved',
        'user_id' => $org['admin']->id,
        'approver_name' => 'Admin',
        'role' => 'Admin',
        'remarks' => 'Old final',
    ]);

    $presented = $service->present($claim->fresh($service->relations()));
    $final = collect($presented['timeline']['stages'])->firstWhere('key', 'final');

    expect($service->queueQuery($org['admin'])->whereKey($claim->id)->exists())->toBeFalse()
        ->and($service->queueQuery($org['director'])->whereKey($claim->id)->exists())->toBeFalse()
        ->and($service->canDecide($org['admin'], $claim->fresh()))->toBeFalse()
        ->and($service->canDecide($org['director'], $claim->fresh()))->toBeFalse()
        ->and($presented['pending_with'])->toBe('Payment')
        ->and(collect($presented['timeline']['stages'])->pluck('key'))->not->toContain('director')
        ->and($final['person_name'])->toBe('Admin')
        ->and($final['role'])->toBe('Admin')
        ->and($final['status_label'])->toBe('Approved')
        ->and($row->fresh()->remarks)->toBe('Old final');
});
