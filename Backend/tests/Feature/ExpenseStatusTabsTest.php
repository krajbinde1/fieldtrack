<?php

use App\Filament\Resources\ExpenseClaims\Pages\ListExpenseClaims;
use App\Models\ExpenseClaim;
use App\Models\User;
use App\Enums\UserRole;
use App\Services\Expenses\ExpenseApproverResolver;
use App\Services\Expenses\ExpenseClaimService;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

function expenseStatusSubmitPayload(): array
{
    return [
        'action' => 'submit',
        'items' => json_encode([[
            'expense_date' => '2026-11-20',
            'type' => 'food',
            'amount' => 250,
            'description' => 'Field lunch',
        ]]),
    ];
}

function expenseStatusCoordinator(array $org, string $login, string $name, ?int $centerId = null): User
{
    $coordinator = User::query()->create([
        'name' => $name,
        'email' => $login.'@test.local',
        'login_id' => $login,
        'password' => Hash::make('Coordinator@123'),
        'role' => UserRole::CenterCoordinator->value,
        'is_active' => true,
    ]);
    $coordinator->coordinatedCenters()->attach($centerId ?? $org['centerA']->id);

    return $coordinator;
}

function expenseStatusManager(string $login, string $name, int $centerId): User
{
    $manager = User::query()->create([
        'name' => $name,
        'email' => $login.'@test.local',
        'login_id' => $login,
        'password' => Hash::make('CenterMgr@123'),
        'role' => UserRole::CenterManager->value,
        'is_active' => true,
    ]);
    $manager->managedCenters()->attach($centerId);

    return $manager;
}

/**
 * @return list<int>
 */
function expenseStatusIds(object $test, User $user, string $status): array
{
    $prefix = $user->isCenterManager() ? '/api/manager/expenses' : '/api/director/expenses';
    $response = $test->actingAs($user, 'sanctum')
        ->getJson($prefix.'?status='.$status)
        ->assertOk();

    $service = app(ExpenseClaimService::class);
    $counts = $service->tabCounts($user);
    foreach ($counts as $key => $count) {
        expect($response->json('counts.'.$key))->toBe($count);
    }

    $ids = collect($response->json('data'))->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
    $queried = $service->tabQuery($user, $status)->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all();

    expect($response->json('pending_count'))->toBe($service->pendingCountFor($user))
        ->and($response->json('counts.'.$status))->toBe(count($ids))
        ->and($ids)->toBe($queried);

    return $ids;
}

function expenseStatusRow(object $test, User $user, string $status, int $id): array
{
    $prefix = $user->isCenterManager() ? '/api/manager/expenses' : '/api/director/expenses';
    $row = collect($test->actingAs($user, 'sanctum')->getJson($prefix.'?status='.$status)->assertOk()->json('data'))
        ->firstWhere('id', $id);
    expect($row)->toBeArray();

    return $row;
}

it('keeps each approver tab aligned from submission through payment', function () {
    $org = seedOrg();
    $coordinator = expenseStatusCoordinator($org, 'tabs-coord', 'Ravi Coordinator');
    $otherManager = expenseStatusManager('tabs-other-cm', 'Other Manager', $org['centerB']->id);
    $otherCoordinator = expenseStatusCoordinator($org, 'tabs-other-coord', 'Other Coordinator', $org['centerB']->id);
    $userB = User::query()->where('login_id', '9000000002')->firstOrFail();
    $service = app(ExpenseClaimService::class);
    $resolver = app(ExpenseApproverResolver::class);
    $manager = $org['centerManager'];
    $admin = $org['admin'];
    $director = $org['director'];

    $created = $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', expenseStatusSubmitPayload())
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending_center_manager');
    $id = (int) $created->json('data.id');
    $claim = ExpenseClaim::query()->findOrFail($id);
    $managerNames = $resolver->managers($org['centerA'], $org['userA']->id)->pluck('name')->all();

    expect(expenseStatusIds($this, $manager, 'pending'))->toContain($id)
        ->and(expenseStatusIds($this, $coordinator, 'pending'))->not->toContain($id)
        ->and(expenseStatusIds($this, $admin, 'pending'))->not->toContain($id)
        ->and(expenseStatusIds($this, $director, 'pending'))->not->toContain($id)
        ->and(expenseStatusIds($this, $manager, 'approved'))->not->toContain($id)
        ->and($service->pendingCountFor($manager))->toBe(1)
        ->and($service->pendingCountFor($coordinator))->toBe(0)
        ->and($service->pendingCountFor($admin))->toBe(0);

    $waiting = expenseStatusRow($this, $manager, 'pending', $id);
    expect($waiting['pending_with'])->toBe('Center Manager — waiting on '.implode(', ', $managerNames))
        ->and($waiting['pending_with'])->toBe($waiting['pending_approval'])
        ->and($waiting['status_label'])->toBe('Pending Center Manager')
        ->and($waiting['timeline']['visible'])->toBeTrue()
        ->and(collect($waiting['timeline']['stages'])->pluck('key'))->toContain('center_manager');

    $this->app['auth']->forgetGuards();
    Livewire::actingAs($manager)
        ->test(ListExpenseClaims::class)
        ->assertCanSeeTableRecords([$claim])
        ->assertSee('Pending With')
        ->assertSee('waiting on CM');
    Livewire::actingAs($coordinator)
        ->test(ListExpenseClaims::class)
        ->assertCanNotSeeTableRecords([$claim]);

    $this->actingAs($manager, 'sanctum')
        ->postJson('/api/manager/expenses/'.$id.'/approve', ['remarks' => 'Checked'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_center_coordinator');

    expect(expenseStatusIds($this, $manager, 'pending'))->not->toContain($id)
        ->and(expenseStatusIds($this, $manager, 'approved'))->toContain($id)
        ->and(expenseStatusIds($this, $coordinator, 'pending'))->toContain($id)
        ->and(expenseStatusIds($this, $coordinator, 'approved'))->not->toContain($id)
        ->and(expenseStatusIds($this, $admin, 'pending'))->not->toContain($id)
        ->and(expenseStatusIds($this, $director, 'pending'))->not->toContain($id)
        ->and(expenseStatusIds($this, $admin, 'approved'))->not->toContain($id)
        ->and($service->pendingCountFor($manager))->toBe(0)
        ->and($service->pendingCountFor($coordinator))->toBe(1);

    $withCoordinator = expenseStatusRow($this, $manager, 'approved', $id);
    expect($withCoordinator['status'])->toBe('pending_center_coordinator')
        ->and($withCoordinator['pending_with'])->toBe('Center Coordinator — waiting on Ravi Coordinator')
        ->and($withCoordinator['timeline']['pending_text'])->toContain('Ravi Coordinator');

    $this->app['auth']->forgetGuards();
    Livewire::actingAs($manager)
        ->test(ListExpenseClaims::class)
        ->assertCanNotSeeTableRecords([$claim]);
    Livewire::actingAs($manager)
        ->test(ListExpenseClaims::class)
        ->set('activeTab', 'approved')
        ->assertCanSeeTableRecords([$claim])
        ->assertSee('waiting on Ravi Coordinator');

    $this->actingAs($coordinator, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve', ['remarks' => 'Center ok'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_director');

    expect(expenseStatusIds($this, $coordinator, 'pending'))->not->toContain($id)
        ->and(expenseStatusIds($this, $coordinator, 'approved'))->toContain($id)
        ->and(expenseStatusIds($this, $manager, 'approved'))->toContain($id)
        ->and(expenseStatusIds($this, $manager, 'pending'))->not->toContain($id)
        ->and(expenseStatusIds($this, $admin, 'pending'))->not->toContain($id)
        ->and(expenseStatusIds($this, $director, 'pending'))->toContain($id)
        ->and(expenseStatusIds($this, $admin, 'approved'))->not->toContain($id)
        ->and(expenseStatusIds($this, $director, 'approved'))->not->toContain($id);

    $withDirector = expenseStatusRow($this, $director, 'pending', $id);
    expect($withDirector['pending_with'])->toBe('Director — waiting on '.$director->name)
        ->and($withDirector['pending_with'])->not->toContain('Admin')
        ->and($withDirector['timeline']['pending_text'])->toBe('Director Approval — waiting on '.$director->name);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve')
        ->assertForbidden();
    $this->actingAs($director, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve', ['remarks' => 'Director ok'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_final');

    expect(expenseStatusIds($this, $director, 'pending'))->not->toContain($id)
        ->and(expenseStatusIds($this, $director, 'approved'))->toContain($id)
        ->and(expenseStatusIds($this, $admin, 'pending'))->toContain($id)
        ->and(expenseStatusIds($this, $admin, 'approved'))->not->toContain($id);

    $withAdmin = expenseStatusRow($this, $admin, 'pending', $id);
    expect($withAdmin['pending_with'])->toBe('Admin — waiting on '.$admin->name)
        ->and($withAdmin['timeline']['pending_text'])->toBe('Admin Final Approval — waiting on '.$admin->name);

    $this->actingAs($director, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/pay', [
            'amount' => 250,
            'payment_mode' => 'upi',
            'paid_on' => '2026-11-23',
        ])
        ->assertForbidden();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve', ['remarks' => 'Final ok'])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.payment_status', 'unpaid')
        ->assertJsonPath('data.pending_with', 'Payment');

    expect(expenseStatusIds($this, $admin, 'pending'))->not->toContain($id)
        ->and(expenseStatusIds($this, $director, 'pending'))->not->toContain($id)
        ->and(expenseStatusIds($this, $admin, 'approved'))->toContain($id)
        ->and(expenseStatusIds($this, $director, 'approved'))->toContain($id)
        ->and(expenseStatusIds($this, $manager, 'approved'))->toContain($id)
        ->and(expenseStatusIds($this, $coordinator, 'approved'))->toContain($id)
        ->and(expenseStatusIds($this, $admin, 'paid'))->not->toContain($id);

    $awaitingPayment = expenseStatusRow($this, $admin, 'approved', $id);
    $paymentStage = collect($awaitingPayment['timeline']['stages'])->firstWhere('key', 'payment');
    expect($awaitingPayment['status_label'])->toBe('Approved / Pending Payment')
        ->and($awaitingPayment['pending_with'])->toBe('Payment')
        ->and($awaitingPayment['pending_approval'])->toBeNull()
        ->and($paymentStage['status'])->toBe('pending')
        ->and($paymentStage['person_name'])->toBeNull();

    $this->actingAs($manager, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/pay', [
            'amount' => 250,
            'payment_mode' => 'upi',
            'paid_on' => '2026-11-23',
        ])
        ->assertForbidden();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/pay', [
            'amount' => 250,
            'payment_mode' => 'upi',
            'reference_number' => 'UPI-TAB',
            'paid_on' => '2026-11-23',
        ])
        ->assertOk()
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonPath('data.pending_with', null)
        ->assertJsonPath('data.status_label', 'Paid');

    foreach ([$manager, $coordinator, $admin, $director] as $viewer) {
        expect(expenseStatusIds($this, $viewer, 'pending'))->not->toContain($id)
            ->and(expenseStatusIds($this, $viewer, 'approved'))->not->toContain($id)
            ->and(expenseStatusIds($this, $viewer, 'rejected'))->not->toContain($id)
            ->and(expenseStatusIds($this, $viewer, 'paid'))->toContain($id);
    }

    foreach ([$manager, $coordinator, $admin] as $handler) {
        $history = expenseStatusRow($this, $handler, 'history', $id);
        $stages = collect($history['timeline']['stages']);
        expect($history['status_label'])->toBe('Paid')
            ->and($history['pending_with'])->toBeNull()
            ->and($history['timeline']['visible'])->toBeTrue()
            ->and($stages->pluck('key')->all())->toContain('created')
            ->and($stages->firstWhere('key', 'center_manager')['person_name'])->toBe('CM')
            ->and($stages->firstWhere('key', 'center_coordinator')['person_name'])->toBe('Ravi Coordinator')
            ->and($stages->firstWhere('key', 'director')['person_name'])->toBe('Director')
            ->and($stages->firstWhere('key', 'admin')['person_name'])->toBe('Admin')
            ->and($stages->firstWhere('key', 'payment')['status_label'])->toBe('Paid');
    }

    expect(expenseStatusIds($this, $director, 'history'))->toContain($id);

    $this->app['auth']->forgetGuards();
    Livewire::actingAs($admin)
        ->test(ListExpenseClaims::class)
        ->set('activeTab', 'approved')
        ->assertCanNotSeeTableRecords([$claim]);
    Livewire::actingAs($admin)
        ->test(ListExpenseClaims::class)
        ->set('activeTab', 'paid')
        ->assertCanSeeTableRecords([$claim])
        ->assertSee('Paid');

    $otherId = (int) $this->actingAs($userB, 'sanctum')
        ->post('/api/expenses', expenseStatusSubmitPayload())
        ->assertCreated()
        ->json('data.id');
    $this->actingAs($otherManager, 'sanctum')
        ->postJson('/api/manager/expenses/'.$otherId.'/approve')
        ->assertOk();
    $this->actingAs($otherCoordinator, 'sanctum')
        ->postJson('/api/director/expenses/'.$otherId.'/approve')
        ->assertOk();
    $this->actingAs($director, 'sanctum')
        ->postJson('/api/director/expenses/'.$otherId.'/approve')
        ->assertOk();
    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/director/expenses/'.$otherId.'/approve')
        ->assertOk();
    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/director/expenses/'.$otherId.'/pay', [
            'amount' => 250,
            'payment_mode' => 'cash',
            'paid_on' => '2026-11-23',
        ])
        ->assertOk();

    expect(expenseStatusIds($this, $otherManager, 'paid'))->toContain($otherId)
        ->and(expenseStatusIds($this, $manager, 'paid'))->not->toContain($otherId)
        ->and(expenseStatusIds($this, $coordinator, 'paid'))->not->toContain($otherId)
        ->and(expenseStatusIds($this, $admin, 'paid'))->toContain($otherId)
        ->and(expenseStatusIds($this, $director, 'paid'))->toContain($otherId)
        ->and(expenseStatusIds($this, $manager, 'paid'))->toContain($id);
});

it('moves a rejection onto the actor rejected tab and keeps other centers out', function () {
    $org = seedOrg();
    $coordinator = expenseStatusCoordinator($org, 'tabs-reject-coord', 'Reject Coordinator');
    $otherManager = expenseStatusManager('tabs-reject-cm', 'Outside Manager', $org['centerB']->id);
    $otherCoordinator = expenseStatusCoordinator($org, 'tabs-reject-out', 'Outside Coordinator', $org['centerB']->id);
    $userB = User::query()->where('login_id', '9000000002')->firstOrFail();
    $manager = $org['centerManager'];
    $admin = $org['admin'];
    $director = $org['director'];

    $managerRejected = (int) $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', expenseStatusSubmitPayload())
        ->assertCreated()
        ->json('data.id');
    $this->actingAs($manager, 'sanctum')
        ->postJson('/api/manager/expenses/'.$managerRejected.'/reject', ['remarks' => 'Bill is unclear'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect(expenseStatusIds($this, $manager, 'pending'))->not->toContain($managerRejected)
        ->and(expenseStatusIds($this, $manager, 'approved'))->not->toContain($managerRejected)
        ->and(expenseStatusIds($this, $manager, 'rejected'))->toContain($managerRejected)
        ->and(expenseStatusIds($this, $coordinator, 'pending'))->not->toContain($managerRejected)
        ->and(expenseStatusIds($this, $coordinator, 'approved'))->not->toContain($managerRejected)
        ->and(expenseStatusIds($this, $coordinator, 'rejected'))->toContain($managerRejected)
        ->and(expenseStatusIds($this, $admin, 'pending'))->not->toContain($managerRejected)
        ->and(expenseStatusIds($this, $director, 'pending'))->not->toContain($managerRejected)
        ->and(expenseStatusIds($this, $admin, 'rejected'))->toContain($managerRejected)
        ->and(expenseStatusIds($this, $director, 'rejected'))->toContain($managerRejected);

    $managerHistory = expenseStatusRow($this, $manager, 'history', $managerRejected);
    expect($managerHistory['status'])->toBe('rejected')
        ->and($managerHistory['pending_with'])->toBeNull()
        ->and($managerHistory['timeline']['visible'])->toBeTrue()
        ->and(collect($managerHistory['timeline']['stages'])->firstWhere('key', 'center_manager')['status_label'])->toBe('Rejected')
        ->and(collect($managerHistory['timeline']['stages'])->firstWhere('key', 'center_manager')['person_name'])->toBe('CM');

    $coordinatorRejected = (int) $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', expenseStatusSubmitPayload())
        ->assertCreated()
        ->json('data.id');
    $this->actingAs($manager, 'sanctum')
        ->postJson('/api/manager/expenses/'.$coordinatorRejected.'/approve')
        ->assertOk();
    $this->actingAs($coordinator, 'sanctum')
        ->postJson('/api/director/expenses/'.$coordinatorRejected.'/reject', ['remarks' => 'Not this month'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect(expenseStatusIds($this, $manager, 'approved'))->not->toContain($coordinatorRejected)
        ->and(expenseStatusIds($this, $manager, 'pending'))->not->toContain($coordinatorRejected)
        ->and(expenseStatusIds($this, $manager, 'rejected'))->toContain($coordinatorRejected)
        ->and(expenseStatusIds($this, $coordinator, 'pending'))->not->toContain($coordinatorRejected)
        ->and(expenseStatusIds($this, $coordinator, 'approved'))->not->toContain($coordinatorRejected)
        ->and(expenseStatusIds($this, $coordinator, 'rejected'))->toContain($coordinatorRejected)
        ->and(expenseStatusIds($this, $admin, 'pending'))->not->toContain($coordinatorRejected)
        ->and(expenseStatusIds($this, $director, 'pending'))->not->toContain($coordinatorRejected)
        ->and(expenseStatusIds($this, $admin, 'approved'))->not->toContain($coordinatorRejected);

    $finalRejected = (int) $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', expenseStatusSubmitPayload())
        ->assertCreated()
        ->json('data.id');
    $this->actingAs($manager, 'sanctum')
        ->postJson('/api/manager/expenses/'.$finalRejected.'/approve')
        ->assertOk();
    $this->actingAs($coordinator, 'sanctum')
        ->postJson('/api/director/expenses/'.$finalRejected.'/approve')
        ->assertOk();
    $this->actingAs($director, 'sanctum')
        ->postJson('/api/director/expenses/'.$finalRejected.'/reject', ['remarks' => 'Amount is too high'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect(expenseStatusIds($this, $director, 'pending'))->not->toContain($finalRejected)
        ->and(expenseStatusIds($this, $director, 'approved'))->not->toContain($finalRejected)
        ->and(expenseStatusIds($this, $director, 'rejected'))->toContain($finalRejected)
        ->and(expenseStatusIds($this, $admin, 'pending'))->not->toContain($finalRejected)
        ->and(expenseStatusIds($this, $admin, 'approved'))->not->toContain($finalRejected)
        ->and(expenseStatusIds($this, $manager, 'approved'))->not->toContain($finalRejected)
        ->and(expenseStatusIds($this, $coordinator, 'approved'))->not->toContain($finalRejected)
        ->and(expenseStatusIds($this, $manager, 'rejected'))->toContain($finalRejected)
        ->and(expenseStatusIds($this, $coordinator, 'rejected'))->toContain($finalRejected)
        ->and(expenseStatusIds($this, $admin, 'rejected'))->toContain($finalRejected);

    $outside = (int) $this->actingAs($userB, 'sanctum')
        ->post('/api/expenses', expenseStatusSubmitPayload())
        ->assertCreated()
        ->json('data.id');
    expect(expenseStatusIds($this, $manager, 'pending'))->not->toContain($outside)
        ->and(expenseStatusIds($this, $coordinator, 'pending'))->not->toContain($outside);
    $this->actingAs($otherManager, 'sanctum')
        ->postJson('/api/manager/expenses/'.$outside.'/reject', ['remarks' => 'Wrong center'])
        ->assertOk();

    expect(expenseStatusIds($this, $otherManager, 'rejected'))->toContain($outside)
        ->and(expenseStatusIds($this, $otherManager, 'pending'))->not->toContain($outside)
        ->and(expenseStatusIds($this, $manager, 'rejected'))->not->toContain($outside)
        ->and(expenseStatusIds($this, $coordinator, 'rejected'))->not->toContain($outside)
        ->and(expenseStatusIds($this, $otherCoordinator, 'rejected'))->toContain($outside)
        ->and(expenseStatusIds($this, $admin, 'rejected'))->toContain($outside)
        ->and(expenseStatusIds($this, $director, 'rejected'))->toContain($outside);
});
