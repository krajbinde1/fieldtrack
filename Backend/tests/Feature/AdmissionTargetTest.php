<?php

use App\Enums\AdmissionStatus;
use App\Enums\AdmissionTargetType;
use App\Filament\Resources\AdmissionTargets\AdmissionTargetResource;
use App\Filament\Resources\AdmissionTargets\Pages\CreateAdmissionTarget;
use App\Filament\Resources\AdmissionTargets\Pages\ListAdmissionTargets;
use App\Filament\Widgets\AdmissionTargetPerformanceWidget;
use App\Models\Admission;
use App\Models\AdmissionTarget;
use App\Services\AdmissionTargetService;
use App\Support\AdmissionTargetPeriod;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('lets a center manager create a weekly target for an assigned-center employee', function () {
    $org = seedOrg();
    [$start] = AdmissionTargetPeriod::resolve('this_week');

    $this->actingAs($org['centerManager']);

    expect(AdmissionTargetResource::canAccess())->toBeTrue()
        ->and(AdmissionTargetResource::canCreate())->toBeTrue();

    Livewire::actingAs($org['centerManager'])
        ->test(CreateAdmissionTarget::class)
        ->fillForm([
            'employee_id' => $org['empA']->id,
            'target_type' => AdmissionTargetType::Weekly->value,
            'target_count' => 8,
            'period' => $start->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $target = AdmissionTarget::query()
        ->where('employee_id', $org['empA']->id)
        ->whereNull('parent_id')
        ->first();

    expect($target)->not->toBeNull()
        ->and($target->target_count)->toBe(8)
        ->and($target->target_type)->toBe(AdmissionTargetType::Weekly)
        ->and($target->center_id)->toBe($org['centerA']->id)
        ->and($target->assigned_by_user_id)->toBe($org['centerManager']->id);
});

it('blocks a center manager from creating a target for an employee outside assigned centers', function () {
    $org = seedOrg();
    [$start] = AdmissionTargetPeriod::resolve('this_week');
    $service = app(AdmissionTargetService::class);

    try {
        $service->assign($org['centerManager'], [
            'employee_id' => $org['empB']->id,
            'target_type' => AdmissionTargetType::Weekly->value,
            'target_count' => 5,
            'period' => $start->toDateString(),
        ]);
        test()->fail('Expected assign() to abort for an employee outside assigned centers.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }

    expect(AdmissionTarget::query()->where('employee_id', $org['empB']->id)->exists())->toBeFalse();
});

it('scopes admission target list and achievement to assigned centers and confirmed admissions', function () {
    $org = seedOrg();
    [$start, $end] = AdmissionTargetPeriod::resolve('this_week');

    $own = AdmissionTarget::create([
        'employee_id' => $org['empA']->id,
        'center_id' => $org['centerA']->id,
        'scheme_id' => $org['projectA']->id,
        'assigned_by_user_id' => $org['centerManager']->id,
        'target_type' => AdmissionTargetType::Weekly,
        'period_start' => $start->toDateString(),
        'period_end' => $end->toDateString(),
        'target_count' => 10,
    ]);
    $hidden = AdmissionTarget::create([
        'employee_id' => $org['empB']->id,
        'center_id' => $org['centerB']->id,
        'scheme_id' => $org['projectB']->id,
        'target_type' => AdmissionTargetType::Weekly,
        'period_start' => $start->toDateString(),
        'period_end' => $end->toDateString(),
        'target_count' => 20,
    ]);

    Admission::create([
        'scheme_id' => $org['projectA']->id,
        'center_id' => $org['centerA']->id,
        'employee_id' => $org['empA']->id,
        'first_name' => 'Submitted',
        'last_name' => 'Skip',
        'status' => AdmissionStatus::Submitted,
        'submitted_at' => $start->copy()->addDay()->setTime(10, 0),
    ]);
    Admission::create([
        'scheme_id' => $org['projectA']->id,
        'center_id' => $org['centerA']->id,
        'employee_id' => $org['empA']->id,
        'first_name' => 'Confirmed',
        'last_name' => 'Count',
        'status' => AdmissionStatus::Confirmed,
        'submitted_at' => $start->copy()->addDay()->setTime(10, 0),
        'confirmed_at' => $start->copy()->addDay()->setTime(12, 0),
    ]);

    $metrics = app(AdmissionTargetService::class)->metricsForTarget($own);

    expect($metrics['achieved'])->toBe(1)
        ->and($metrics['remaining'])->toBe(9)
        ->and($metrics['percentage'])->toBe(10.0);

    $this->actingAs($org['centerManager']);

    Livewire::actingAs($org['centerManager'])
        ->test(ListAdmissionTargets::class)
        ->assertCanSeeTableRecords([$own])
        ->assertCanNotSeeTableRecords([$hidden]);

    $team = app(AdmissionTargetService::class)->teamPerformance($org['centerManager'], 'this_week');

    expect($team['target'])->toBe(10)
        ->and($team['achieved'])->toBe(1)
        ->and($team['remaining'])->toBe(9);

    expect(AdmissionTargetPerformanceWidget::canView())->toBeTrue();
});
