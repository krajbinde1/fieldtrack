<?php

use App\Enums\CenterStaffRole;
use App\Enums\UserRole;
use App\Filament\Resources\ExpenseClaims\Pages\ViewExpenseClaim;
use App\Models\Employee;
use App\Models\ExpenseApproval;
use App\Models\ExpenseClaim;
use App\Models\User;
use App\Services\Expenses\ExpenseApproverResolver;
use App\Services\Expenses\ExpenseClaimService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

function expenseTimelinePayload(bool $submit = true): array
{
    return [
        'action' => $submit ? 'submit' : 'draft',
        'items' => json_encode([[
            'expense_date' => '2026-11-20',
            'type' => 'food',
            'amount' => 250,
            'description' => 'Field lunch',
        ]]),
        'receipts' => [
            UploadedFile::fake()->image('receipt.jpg'),
        ],
    ];
}

function expenseTimelineCoordinator(array $org, string $login, string $name): User
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

function expenseTimelineStaff(array $org): User
{
    $employee = Employee::query()->create([
        'center_id' => $org['centerA']->id,
        'full_name' => 'Staff Trainer',
        'mobile' => '9000000099',
        'status' => true,
        'staff_role' => CenterStaffRole::Trainer->value,
    ]);

    return User::query()->create([
        'employee_id' => $employee->id,
        'name' => 'Staff Trainer',
        'email' => 'staff-trainer@test.local',
        'login_id' => '9000000099',
        'password' => Hash::make('Employee@123'),
        'role' => UserRole::Employee->value,
        'is_active' => true,
    ]);
}

/**
 * @param  array<string, mixed>  $timeline
 * @return array<string, mixed>
 */
function expenseTimelineStage(array $timeline, string $key): array
{
    $stage = collect($timeline['stages'])->firstWhere('key', $key);
    expect($stage)->toBeArray();

    return $stage;
}

function expenseTimelineFrom($test, User $user, string $path): array
{
    return $test->actingAs($user, 'sanctum')
        ->getJson($path)
        ->assertOk()
        ->json('data.timeline');
}

it('shows the same approval timeline to every role and follows each stage', function () {
    $org = seedOrg();
    $org['empA']->update(['staff_role' => CenterStaffRole::Mobilizer->value]);
    $coordinator = expenseTimelineCoordinator($org, 'timeline-coord', 'Ravi Coordinator');
    $staff = expenseTimelineStaff($org);
    $service = app(ExpenseClaimService::class);

    $draft = $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', expenseTimelinePayload(false))
        ->assertCreated()
        ->json('data.timeline');
    expect($draft['visible'])->toBeFalse()
        ->and($draft['stages'])->toBe([]);

    $created = $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', expenseTimelinePayload())
        ->assertCreated();
    $id = $created->json('data.id');
    $claim = ExpenseClaim::query()->findOrFail($id);

    expect($service->canDecide($org['userA'], $claim))->toBeFalse()
        ->and($service->canDecide($staff, $claim))->toBeFalse()
        ->and($service->canDecide($org['centerManager'], $claim))->toBeTrue()
        ->and($service->canDecide($coordinator, $claim))->toBeFalse()
        ->and($service->canDecide($org['director'], $claim))->toBeFalse()
        ->and($service->canDecide($org['admin'], $claim))->toBeFalse();

    $this->actingAs($org['userA'], 'sanctum')
        ->postJson('/api/manager/expenses/'.$id.'/approve')
        ->assertForbidden();
    $this->actingAs($staff, 'sanctum')
        ->getJson('/api/expenses/'.$id)
        ->assertForbidden();
    $this->actingAs($org['admin'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve')
        ->assertForbidden();

    $viewers = [
        'mobilizer' => [$org['userA'], '/api/expenses/'.$id],
        'center_manager' => [$org['centerManager'], '/api/manager/expenses/'.$id],
        'coordinator' => [$coordinator, '/api/director/expenses/'.$id],
        'director' => [$org['director'], '/api/director/expenses/'.$id],
        'admin' => [$org['admin'], '/api/director/expenses/'.$id],
    ];
    $timelines = [];
    foreach ($viewers as $role => [$user, $path]) {
        $timelines[$role] = expenseTimelineFrom($this, $user, $path);
    }
    foreach ($timelines as $timeline) {
        expect($timeline)->toEqual($timelines['mobilizer']);
    }

    $submitted = $timelines['mobilizer'];
    $rowCount = ExpenseApproval::query()->where('expense_claim_id', $id)->count();
    expect(app(\App\Services\Expenses\ExpenseApprovalTimeline::class)->forClaim($claim->fresh()))->toEqual($submitted)
        ->and(ExpenseApproval::query()->where('expense_claim_id', $id)->count())->toBe($rowCount)
        ->and(collect($submitted['stages'])->pluck('key')->all())->toBe([
            'created', 'center_manager', 'center_coordinator', 'director', 'admin', 'payment',
        ])
        ->and($submitted['pending_text'])->toBe('Center Manager Approval — waiting on CM');

    $createdStage = expenseTimelineStage($submitted, 'created');
    expect($createdStage['person_name'])->toBe('Emp A')
        ->and($createdStage['role'])->toBe('Mobilizer')
        ->and($createdStage['color'])->toBe('green')
        ->and($createdStage['status'])->toBe('completed')
        ->and($createdStage['status_label'])->toBe('Submitted')
        ->and($createdStage['date'])->not->toBeNull()
        ->and($createdStage['time'])->not->toBeNull();

    $managerStage = expenseTimelineStage($submitted, 'center_manager');
    expect($managerStage['color'])->toBe('orange')
        ->and($managerStage['status_label'])->toBe('Pending')
        ->and($managerStage['person_name'])->toBe('CM')
        ->and($managerStage['role'])->toBe('Center Manager')
        ->and($managerStage['pending_names'])->toBe(['CM']);

    expect(expenseTimelineStage($submitted, 'center_coordinator')['color'])->toBe('grey')
        ->and(expenseTimelineStage($submitted, 'center_coordinator')['status_label'])->toBe('Not Started')
        ->and(expenseTimelineStage($submitted, 'center_coordinator')['person_name'])->toBeNull()
        ->and(expenseTimelineStage($submitted, 'director')['color'])->toBe('grey')
        ->and(expenseTimelineStage($submitted, 'admin')['color'])->toBe('grey')
        ->and(expenseTimelineStage($submitted, 'payment')['color'])->toBe('grey')
        ->and(expenseTimelineStage($submitted, 'payment')['status'])->toBe('not_started');

    $html = view('filament.expenses.approval-timeline', [
        'getState' => fn (): array => $submitted,
    ])->render();
    expect($html)->toContain('Request Created')
        ->and($html)->toContain('Emp A')
        ->and($html)->toContain('Mobilizer')
        ->and($html)->toContain('Center Manager Approval')
        ->and($html)->toContain('CM')
        ->and($html)->toContain('waiting on CM');

    foreach ([$org['admin'], $org['director'], $coordinator, $org['centerManager']] as $viewer) {
        $this->app['auth']->forgetGuards();
        Livewire::actingAs($viewer)
            ->test(ViewExpenseClaim::class, ['record' => $id])
            ->assertSee('Request Created')
            ->assertSee('Emp A')
            ->assertSee('Center Manager Approval')
            ->assertSee('waiting on CM');
    }

    $staffId = $this->actingAs($staff, 'sanctum')
        ->post('/api/expenses', expenseTimelinePayload())
        ->assertCreated()
        ->json('data.id');
    $staffTimeline = expenseTimelineFrom($this, $staff, '/api/expenses/'.$staffId);
    expect(collect($staffTimeline['stages'])->pluck('key')->all())->toBe(collect($submitted['stages'])->pluck('key')->all())
        ->and(expenseTimelineStage($staffTimeline, 'created')['person_name'])->toBe('Staff Trainer')
        ->and(expenseTimelineStage($staffTimeline, 'created')['role'])->toBe('Trainer')
        ->and(expenseTimelineStage($staffTimeline, 'created')['color'])->toBe('green')
        ->and(expenseTimelineStage($staffTimeline, 'center_manager')['pending_names'])->toBe(['CM'])
        ->and(expenseTimelineStage($staffTimeline, 'center_manager')['color'])->toBe('orange');

    $this->actingAs($org['centerManager'], 'sanctum')
        ->postJson('/api/manager/expenses/'.$id.'/approve', ['remarks' => 'Checked by manager'])
        ->assertOk();
    $afterManager = expenseTimelineFrom($this, $org['userA'], '/api/expenses/'.$id);
    expect(expenseTimelineFrom($this, $coordinator, '/api/director/expenses/'.$id))->toEqual($afterManager)
        ->and(expenseTimelineFrom($this, $org['admin'], '/api/director/expenses/'.$id))->toEqual($afterManager);
    $managerRow = ExpenseApproval::query()->where('expense_claim_id', $id)->where('level', 1)->firstOrFail();
    $approvedManager = expenseTimelineStage($afterManager, 'center_manager');
    expect($approvedManager['person_name'])->toBe($managerRow->approver_name)
        ->and($approvedManager['person_name'])->toBe('CM')
        ->and($approvedManager['role'])->toBe($managerRow->role)
        ->and($approvedManager['role'])->toBe('Center Manager')
        ->and($approvedManager['color'])->toBe('green')
        ->and($approvedManager['status_label'])->toBe('Approved')
        ->and($approvedManager['remarks'])->toBe('Checked by manager')
        ->and($approvedManager['date'])->toBe($managerRow->created_at?->format('d M Y'))
        ->and($approvedManager['time'])->toBe($managerRow->created_at?->format('h:i A'));
    $coordinatorStage = expenseTimelineStage($afterManager, 'center_coordinator');
    expect($coordinatorStage['color'])->toBe('orange')
        ->and($coordinatorStage['pending_names'])->toBe(['Ravi Coordinator'])
        ->and($coordinatorStage['person_name'])->toBe('Ravi Coordinator')
        ->and($afterManager['pending_text'])->toBe('Center Coordinator Approval — waiting on Ravi Coordinator')
        ->and(expenseTimelineStage($afterManager, 'director')['color'])->toBe('grey')
        ->and(expenseTimelineStage($afterManager, 'admin')['color'])->toBe('grey')
        ->and(expenseTimelineStage($afterManager, 'payment')['color'])->toBe('grey')
        ->and($service->canDecide($coordinator, $claim->fresh()))->toBeTrue()
        ->and($service->canDecide($org['centerManager'], $claim->fresh()))->toBeFalse();

    $this->actingAs($coordinator, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve', ['remarks' => 'Center ok'])
        ->assertOk();
    $afterCoordinator = expenseTimelineFrom($this, $org['director'], '/api/director/expenses/'.$id);
    $coordinatorRow = ExpenseApproval::query()->where('expense_claim_id', $id)->where('level', 2)->firstOrFail();
    $approvedCoordinator = expenseTimelineStage($afterCoordinator, 'center_coordinator');
    $directorNames = app(ExpenseApproverResolver::class)->directors($org['centerA'], $org['userA']->id)->pluck('name')->all();
    expect($approvedCoordinator['person_name'])->toBe($coordinatorRow->approver_name)
        ->and($approvedCoordinator['role'])->toBe($coordinatorRow->role)
        ->and($approvedCoordinator['color'])->toBe('green')
        ->and($approvedCoordinator['remarks'])->toBe('Center ok')
        ->and(expenseTimelineStage($afterCoordinator, 'director')['color'])->toBe('orange')
        ->and(expenseTimelineStage($afterCoordinator, 'director')['pending_names'])->toBe($directorNames)
        ->and(expenseTimelineStage($afterCoordinator, 'director')['person_name'])->toBe(implode(', ', $directorNames))
        ->and(expenseTimelineStage($afterCoordinator, 'admin')['color'])->toBe('grey')
        ->and(expenseTimelineStage($afterCoordinator, 'payment')['color'])->toBe('grey')
        ->and($service->canDecide($org['admin'], $claim->fresh()))->toBeFalse()
        ->and($service->canDecide($org['director'], $claim->fresh()))->toBeTrue()
        ->and($service->canDecide($coordinator, $claim->fresh()))->toBeFalse();

    $this->actingAs($org['director'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve', ['remarks' => 'Director ok'])
        ->assertOk();
    $afterDirector = expenseTimelineFrom($this, $org['admin'], '/api/director/expenses/'.$id);
    $directorRow = ExpenseApproval::query()->where('expense_claim_id', $id)->where('level', 3)->firstOrFail();
    $adminNames = app(ExpenseApproverResolver::class)->admins($org['userA']->id)->pluck('name')->all();
    expect(expenseTimelineStage($afterDirector, 'director')['person_name'])->toBe($directorRow->approver_name)
        ->and(expenseTimelineStage($afterDirector, 'director')['role'])->toBe('Director')
        ->and(expenseTimelineStage($afterDirector, 'director')['color'])->toBe('green')
        ->and(expenseTimelineStage($afterDirector, 'admin')['color'])->toBe('orange')
        ->and(expenseTimelineStage($afterDirector, 'admin')['pending_names'])->toBe($adminNames)
        ->and(expenseTimelineStage($afterDirector, 'payment')['color'])->toBe('grey')
        ->and($service->canDecide($org['admin'], $claim->fresh()))->toBeTrue()
        ->and($service->canDecide($org['director'], $claim->fresh()))->toBeFalse();

    $this->actingAs($org['admin'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve', ['remarks' => 'Final ok'])
        ->assertOk();
    $approved = expenseTimelineFrom($this, $org['admin'], '/api/director/expenses/'.$id);
    $adminRow = ExpenseApproval::query()->where('expense_claim_id', $id)->where('level', 4)->firstOrFail();
    expect(expenseTimelineStage($approved, 'admin')['person_name'])->toBe($adminRow->approver_name)
        ->and(expenseTimelineStage($approved, 'admin')['role'])->toBe($adminRow->role)
        ->and(expenseTimelineStage($approved, 'admin')['role'])->toBe('Admin')
        ->and(expenseTimelineStage($approved, 'admin')['color'])->toBe('green')
        ->and(expenseTimelineStage($approved, 'payment')['color'])->toBe('orange')
        ->and(expenseTimelineStage($approved, 'payment')['status_label'])->toBe('Pending')
        ->and(expenseTimelineStage($approved, 'payment')['person_name'])->toBeNull()
        ->and($approved['pending_text'])->toBeNull()
        ->and($service->canDecide($org['admin'], $claim->fresh()))->toBeFalse();

    $this->actingAs($coordinator, 'sanctum')
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
            'reference_number' => 'UPI250',
            'paid_on' => '2026-11-23',
            'remarks' => 'Transferred',
        ])
        ->assertOk();
    $paid = expenseTimelineFrom($this, $org['userA'], '/api/expenses/'.$id);
    expect(expenseTimelineFrom($this, $org['centerManager'], '/api/manager/expenses/'.$id))->toEqual($paid)
        ->and(expenseTimelineFrom($this, $org['admin'], '/api/director/expenses/'.$id))->toEqual($paid);
    $this->actingAs($staff, 'sanctum')
        ->getJson('/api/expenses/'.$id)
        ->assertForbidden();
    $payment = expenseTimelineStage($paid, 'payment');
    expect($payment['color'])->toBe('green')
        ->and($payment['status_label'])->toBe('Paid')
        ->and($payment['person_name'])->toBe('Admin')
        ->and($payment['role'])->toBe('Admin')
        ->and($payment['date'])->toBe('23 Nov 2026')
        ->and($payment['remarks'])->toBe('Transferred')
        ->and(ExpenseApproval::query()->where('expense_claim_id', $id)->count())->toBe(4);
});

it('keeps later timeline stages grey after a rejection', function () {
    $org = seedOrg();
    $coordinator = expenseTimelineCoordinator($org, 'timeline-reject', 'Reject Coordinator');
    $id = $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', expenseTimelinePayload())
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($org['centerManager'], 'sanctum')
        ->postJson('/api/manager/expenses/'.$id.'/reject', ['remarks' => 'Bill is unclear'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    $timeline = expenseTimelineFrom($this, $org['userA'], '/api/expenses/'.$id);
    expect(expenseTimelineFrom($this, $org['director'], '/api/director/expenses/'.$id))->toEqual($timeline)
        ->and(expenseTimelineFrom($this, $coordinator, '/api/director/expenses/'.$id))->toEqual($timeline)
        ->and(expenseTimelineFrom($this, $org['admin'], '/api/director/expenses/'.$id))->toEqual($timeline);

    $row = ExpenseApproval::query()->where('expense_claim_id', $id)->where('level', 1)->firstOrFail();
    $manager = expenseTimelineStage($timeline, 'center_manager');
    expect($manager['color'])->toBe('red')
        ->and($manager['status_label'])->toBe('Rejected')
        ->and($manager['person_name'])->toBe($row->approver_name)
        ->and($manager['role'])->toBe($row->role)
        ->and($manager['remarks'])->toBe('Bill is unclear')
        ->and(expenseTimelineStage($timeline, 'center_coordinator')['color'])->toBe('grey')
        ->and(expenseTimelineStage($timeline, 'center_coordinator')['status'])->toBe('not_started')
        ->and(expenseTimelineStage($timeline, 'director')['color'])->toBe('grey')
        ->and(expenseTimelineStage($timeline, 'admin')['color'])->toBe('grey')
        ->and(expenseTimelineStage($timeline, 'payment')['color'])->toBe('grey')
        ->and(expenseTimelineStage($timeline, 'payment')['status_label'])->toBe('Not Started')
        ->and($timeline['pending_text'])->toBeNull();

    $this->actingAs($coordinator, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve')
        ->assertForbidden();
    $this->actingAs($org['admin'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/pay', [
            'amount' => 250,
            'payment_mode' => 'cash',
            'paid_on' => '2026-11-23',
        ])
        ->assertStatus(422);
});

it('skips manager approval for a center manager claim and names a missing approver', function () {
    $org = seedOrg();
    $service = app(ExpenseClaimService::class);

    $blocked = $this->actingAs($org['centerManager'], 'sanctum')
        ->post('/api/expenses', expenseTimelinePayload())
        ->assertCreated();
    $blockedId = $blocked->json('data.id');
    $blockedTimeline = $blocked->json('data.timeline');

    expect($blocked->json('data.status'))->toBe('needs_resolution')
        ->and($blocked->json('data.pending_approval'))->toBe('Center Coordinator — no approver is assigned')
        ->and(collect($blockedTimeline['stages'])->pluck('key')->all())->toBe([
            'created', 'center_coordinator', 'director', 'admin', 'payment',
        ])
        ->and(expenseTimelineStage($blockedTimeline, 'created')['person_name'])->toBe('CM')
        ->and(expenseTimelineStage($blockedTimeline, 'created')['role'])->toBe('Center Manager')
        ->and(expenseTimelineStage($blockedTimeline, 'created')['color'])->toBe('green')
        ->and(expenseTimelineStage($blockedTimeline, 'center_coordinator')['color'])->toBe('orange')
        ->and(expenseTimelineStage($blockedTimeline, 'center_coordinator')['pending_names'])->toBe([])
        ->and(expenseTimelineStage($blockedTimeline, 'center_coordinator')['detail'])->toBe('No approver is assigned')
        ->and(expenseTimelineStage($blockedTimeline, 'center_coordinator')['person_name'])->toBeNull()
        ->and($blockedTimeline['pending_text'])->toBe('Center Coordinator Approval — no approver is assigned')
        ->and(expenseTimelineStage($blockedTimeline, 'director')['color'])->toBe('grey')
        ->and(expenseTimelineStage($blockedTimeline, 'admin')['color'])->toBe('grey')
        ->and(expenseTimelineStage($blockedTimeline, 'payment')['color'])->toBe('grey')
        ->and($service->canDecide($org['admin'], ExpenseClaim::query()->findOrFail($blockedId)))->toBeFalse();

    $this->actingAs($org['centerManager'], 'sanctum')
        ->postJson('/api/manager/expenses/'.$blockedId.'/approve')
        ->assertForbidden();

    $coordinator = expenseTimelineCoordinator($org, 'timeline-cm', 'Assigned Coordinator');
    $own = $this->actingAs($org['centerManager'], 'sanctum')
        ->post('/api/expenses', expenseTimelinePayload())
        ->assertCreated();
    $ownTimeline = $own->json('data.timeline');

    expect($own->json('data.status'))->toBe('pending_center_coordinator')
        ->and(collect($ownTimeline['stages'])->pluck('key')->all())->not->toContain('center_manager')
        ->and(expenseTimelineStage($ownTimeline, 'center_coordinator')['color'])->toBe('orange')
        ->and(expenseTimelineStage($ownTimeline, 'center_coordinator')['pending_names'])->toBe(['Assigned Coordinator'])
        ->and(expenseTimelineStage($ownTimeline, 'center_coordinator')['person_name'])->toBe($coordinator->name)
        ->and($ownTimeline['pending_text'])->toBe('Center Coordinator Approval — waiting on Assigned Coordinator')
        ->and($service->canDecide($org['centerManager'], ExpenseClaim::query()->findOrFail($own->json('data.id'))))->toBeFalse()
        ->and($service->canDecide($coordinator, ExpenseClaim::query()->findOrFail($own->json('data.id'))))->toBeTrue();
});
