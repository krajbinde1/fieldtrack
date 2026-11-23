<?php

use App\Enums\UserRole;
use App\Filament\Resources\OrgUsers\OrgUserResource;
use App\Filament\Resources\OrgUsers\Pages\CreateOrgUser;
use App\Filament\Resources\OrgUsers\Pages\EditOrgUser;
use App\Models\AppNotification;
use App\Models\Attendance;
use App\Models\Center;
use App\Models\User;
use App\Services\Expenses\ExpenseClaimService;
use App\Support\AttendanceCalendar;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

function assignmentCoordinator(array $org): User
{
    $coordinator = User::query()->create([
        'name' => 'Assignment Coordinator',
        'email' => 'assign-coord@test.local',
        'login_id' => 'assigncoord',
        'password' => Hash::make('Coordinator@123'),
        'role' => UserRole::CenterCoordinator->value,
        'is_active' => true,
    ]);
    $coordinator->coordinatedCenters()->attach($org['centerA']->id);

    return $coordinator;
}

function assignmentExpensePayload(): array
{
    return [
        'action' => 'submit',
        'items' => json_encode([[
            'expense_date' => '2026-11-20',
            'type' => 'food',
            'amount' => 180,
            'description' => 'Assigned center lunch',
            'travel_from' => 'Center',
            'travel_to' => 'Village',
            'travel_km' => 8,
        ]]),
        'receipts' => [
            UploadedFile::fake()->image('receipt.jpg'),
        ],
    ];
}

it('shows assign centers only for a director and saves one many and all centers', function () {
    $org = seedOrg();
    $third = Center::create([
        'scheme_id' => $org['projectB']->id,
        'name' => 'Center C',
        'code' => 'CC',
        'is_active' => true,
    ]);
    $allIds = [$org['centerA']->id, $org['centerB']->id, $third->id];

    Livewire::actingAs($org['admin'])
        ->test(CreateOrgUser::class)
        ->fillForm(['role' => UserRole::CenterManager->value])
        ->assertFormFieldHidden('directedCenters')
        ->fillForm(['role' => UserRole::CenterCoordinator->value])
        ->assertFormFieldHidden('directedCenters')
        ->fillForm(['role' => UserRole::Director->value])
        ->assertFormFieldVisible('directedCenters');

    Livewire::actingAs($org['admin'])
        ->test(CreateOrgUser::class)
        ->fillForm([
            'role' => UserRole::Director->value,
            'name' => 'One Center Director',
            'email' => 'one-dir@test.local',
            'login_id' => 'onedir',
            'password' => 'Director@123',
            'directedCenters' => [$org['centerA']->id],
            'is_active' => true,
            'must_change_password' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $one = User::query()->where('login_id', 'onedir')->firstOrFail();
    expect($one->directedCenters()->pluck('centers.id')->all())->toBe([$org['centerA']->id])
        ->and($one->coordinatedCenters()->count())->toBe(0)
        ->and($one->managedCenters()->count())->toBe(0);

    $editor = Livewire::actingAs($org['admin'])
        ->test(EditOrgUser::class, ['record' => $one->getKey()])
        ->assertFormFieldVisible('directedCenters');
    expect(collect($editor->get('data.directedCenters'))->map(fn ($id): int => (int) $id)->all())
        ->toEqualCanonicalizing([$org['centerA']->id]);

    $editor->fillForm([
        'directedCenters' => [$org['centerA']->id, $org['centerB']->id],
    ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($one->fresh()->directedCenters()->pluck('centers.id')->all())
        ->toEqualCanonicalizing([$org['centerA']->id, $org['centerB']->id]);

    Livewire::actingAs($org['admin'])
        ->test(EditOrgUser::class, ['record' => $one->getKey()])
        ->fillForm(['directedCenters' => $allIds])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($one->fresh()->directedCenters()->pluck('centers.id')->all())
        ->toEqualCanonicalizing($allIds);

    Livewire::actingAs($org['admin'])
        ->test(EditOrgUser::class, ['record' => $one->getKey()])
        ->fillForm(['directedCenters' => [$third->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($one->fresh()->directedCenters()->pluck('centers.id')->all())->toBe([$third->id])
        ->and($org['centerManager']->fresh()->managedCenters()->pluck('centers.id')->all())->toBe([$org['centerA']->id]);

    $this->actingAs($org['director']);
    expect(OrgUserResource::canEdit($one))->toBeFalse()
        ->and(OrgUserResource::canEdit($org['director']))->toBeFalse();

    expect(fn () => Livewire::actingAs($org['director'])
        ->test(EditOrgUser::class, ['record' => $org['director']->getKey()]))
        ->toThrow(ModelNotFoundException::class);
});

it('does not require assign centers for other roles or change their assignments', function () {
    $org = seedOrg();
    $managerCenters = $org['centerManager']->managedCenters()->pluck('centers.id')->all();
    $beforeDirector = $org['director']->directedCenters()->pluck('centers.id')->all();

    Livewire::actingAs($org['admin'])
        ->test(CreateOrgUser::class)
        ->fillForm([
            'role' => UserRole::CenterCoordinator->value,
            'name' => 'Kept Coordinator',
            'email' => 'kept-coord@test.local',
            'login_id' => 'keptcoord',
            'password' => 'Coordinator@123',
            'coordinatedCenters' => [$org['centerB']->id],
            'is_active' => true,
            'must_change_password' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $coordinator = User::query()->where('login_id', 'keptcoord')->firstOrFail();
    expect($coordinator->coordinatedCenters()->pluck('centers.id')->all())->toBe([$org['centerB']->id])
        ->and($coordinator->directedCenters()->count())->toBe(0)
        ->and($coordinator->managedCenters()->count())->toBe(0);

    Livewire::actingAs($org['admin'])
        ->test(EditOrgUser::class, ['record' => $coordinator->getKey()])
        ->assertFormFieldHidden('directedCenters')
        ->fillForm(['name' => 'Kept Coordinator Renamed'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($coordinator->fresh()->name)->toBe('Kept Coordinator Renamed')
        ->and($coordinator->fresh()->coordinatedCenters()->pluck('centers.id')->all())->toBe([$org['centerB']->id])
        ->and($coordinator->fresh()->directedCenters()->count())->toBe(0);

    Livewire::actingAs($org['admin'])
        ->test(EditOrgUser::class, ['record' => $org['centerManager']->getKey()])
        ->assertFormFieldHidden('directedCenters')
        ->fillForm(['name' => 'CM Renamed'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($org['centerManager']->fresh()->managedCenters()->pluck('centers.id')->all())->toBe($managerCenters)
        ->and($org['centerManager']->fresh()->directedCenters()->count())->toBe(0)
        ->and($org['director']->fresh()->directedCenters()->pluck('centers.id')->all())->toEqualCanonicalizing($beforeDirector);
});

it('lets a director see employees attendance and expenses only for assigned centers', function () {
    $org = seedOrg();
    $org['director']->directedCenters()->sync([$org['centerA']->id]);
    $org['centerA']->update(['primary_center_manager_id' => $org['centerManager']->id]);
    $coordinator = assignmentCoordinator($org);
    $otherDirector = User::query()->create([
        'name' => 'Other Director',
        'email' => 'other-dir@test.local',
        'login_id' => 'otherdir',
        'password' => Hash::make('Director@123'),
        'role' => UserRole::Director->value,
        'is_active' => true,
    ]);
    $otherDirector->directedCenters()->attach($org['centerB']->id);

    $own = Attendance::create([
        'employee_id' => $org['empA']->id,
        'attendance_date' => AttendanceCalendar::today()->toDateString(),
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);
    $other = Attendance::create([
        'employee_id' => $org['empB']->id,
        'attendance_date' => AttendanceCalendar::today()->toDateString(),
        'punch_in_time' => '09:00:00',
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);

    $employeeIds = collect($this->actingAs($org['director'], 'sanctum')->getJson('/api/director/employees')->assertOk()->json('data'))
        ->pluck('employee_id')
        ->filter()
        ->map(fn ($id): int => (int) $id)
        ->all();
    expect($employeeIds)->toContain($org['empA']->id)
        ->and($employeeIds)->not->toContain($org['empB']->id);

    $this->actingAs($org['director'], 'sanctum')->getJson('/api/director/team-attendance/'.$own->id)->assertOk();
    $this->actingAs($org['director'], 'sanctum')->getJson('/api/director/team-attendance/'.$other->id)->assertForbidden();
    $this->actingAs($otherDirector, 'sanctum')->getJson('/api/director/team-attendance/'.$own->id)->assertForbidden();

    $created = $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/expenses', assignmentExpensePayload())
        ->assertCreated();
    $id = $created->json('data.id');
    $service = app(ExpenseClaimService::class);

    expect($service->queueQuery($org['director'])->whereKey($id)->exists())->toBeFalse()
        ->and($service->queueQuery($otherDirector)->whereKey($id)->exists())->toBeFalse();

    $this->actingAs($org['director'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve')
        ->assertForbidden();

    $this->actingAs($org['centerManager'], 'sanctum')
        ->postJson('/api/manager/expenses/'.$id.'/approve', ['remarks' => 'Checked'])
        ->assertOk();

    expect($service->queueQuery($org['director'])->whereKey($id)->exists())->toBeFalse();

    $this->actingAs($coordinator, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve', ['remarks' => 'Center ok'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_director');

    expect($service->queueQuery($org['director'])->whereKey($id)->exists())->toBeTrue()
        ->and($service->queueQuery($otherDirector)->whereKey($id)->exists())->toBeFalse()
        ->and($service->queueQuery($org['admin'])->whereKey($id)->exists())->toBeFalse()
        ->and(AppNotification::query()->where('user_id', $org['director']->id)->where('type', 'expense_pending_level_3')->count())->toBe(1)
        ->and(AppNotification::query()->where('user_id', $otherDirector->id)->where('type', 'expense_pending_level_3')->count())->toBe(0);

    $this->actingAs($otherDirector, 'sanctum')
        ->getJson('/api/director/expenses/'.$id)
        ->assertForbidden();
    $this->actingAs($otherDirector, 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve')
        ->assertForbidden();

    $this->actingAs($org['director'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/approve', ['remarks' => 'Director ok'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_final');

    expect($service->queueQuery($org['admin'])->whereKey($id)->exists())->toBeTrue()
        ->and($service->queueQuery($org['director'])->whereKey($id)->exists())->toBeFalse();

    $this->actingAs($org['director'], 'sanctum')
        ->postJson('/api/director/expenses/'.$id.'/pay', [
            'amount' => 180,
            'payment_mode' => 'upi',
            'paid_on' => '2026-11-23',
        ])
        ->assertForbidden();

    $this->actingAs($org['admin'], 'sanctum')
        ->getJson('/api/director/expenses?status=pending')
        ->assertOk()
        ->assertJsonFragment(['id' => $id]);
});
