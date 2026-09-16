<?php

use App\Enums\AdmissionStatus;
use App\Enums\AdmissionTargetType;
use App\Models\Admission;
use App\Models\AdmissionTarget;
use App\Support\AdmissionTargetPeriod;
use Laravel\Sanctum\Sanctum;

it('exposes this-week target performance using submitted admissions only', function () {
    $org = seedOrg();
    [$start, $end] = AdmissionTargetPeriod::resolve('this_week');

    AdmissionTarget::create([
        'employee_id' => $org['empA']->id,
        'center_id' => $org['centerA']->id,
        'scheme_id' => $org['projectA']->id,
        'target_type' => AdmissionTargetType::Weekly,
        'period_start' => $start->toDateString(),
        'period_end' => $end->toDateString(),
        'target_count' => 10,
    ]);

    Admission::create([
        'scheme_id' => $org['projectA']->id,
        'center_id' => $org['centerA']->id,
        'employee_id' => $org['empA']->id,
        'first_name' => 'Draft',
        'last_name' => 'Skip',
        'status' => AdmissionStatus::Draft,
    ]);

    Admission::create([
        'scheme_id' => $org['projectA']->id,
        'center_id' => $org['centerA']->id,
        'employee_id' => $org['empA']->id,
        'first_name' => 'Submitted',
        'last_name' => 'Count',
        'status' => AdmissionStatus::Submitted,
        'submitted_at' => $start->copy()->addDay()->setTime(10, 0),
    ]);

    Sanctum::actingAs($org['userA']);

    $this->getJson('/api/admissions/targets/summary?preset=this_week')
        ->assertOk()
        ->assertJsonPath('data.target', 10)
        ->assertJsonPath('data.achieved', 1)
        ->assertJsonPath('data.remaining', 9)
        ->assertJsonPath('data.period.preset', 'this_week');

    $this->getJson('/api/admissions/targets')
        ->assertOk()
        ->assertJsonPath('data.0.target_count', 10)
        ->assertJsonPath('data.0.achieved', 1)
        ->assertJsonPath('data.0.remaining', 9);
});

it('keeps employee target history scoped to the signed-in employee', function () {
    $org = seedOrg();
    [$start, $end] = AdmissionTargetPeriod::resolve('this_week');

    AdmissionTarget::create([
        'employee_id' => $org['empA']->id,
        'center_id' => $org['centerA']->id,
        'scheme_id' => $org['projectA']->id,
        'target_type' => AdmissionTargetType::Weekly,
        'period_start' => $start->toDateString(),
        'period_end' => $end->toDateString(),
        'target_count' => 4,
    ]);
    AdmissionTarget::create([
        'employee_id' => $org['empB']->id,
        'center_id' => $org['centerB']->id,
        'scheme_id' => $org['projectB']->id,
        'target_type' => AdmissionTargetType::Weekly,
        'period_start' => $start->toDateString(),
        'period_end' => $end->toDateString(),
        'target_count' => 8,
    ]);

    Sanctum::actingAs($org['userA']);

    $items = $this->getJson('/api/admissions/targets')->assertOk()->json('data');

    expect($items)->toHaveCount(1)
        ->and($items[0]['employee_id'])->toBe($org['empA']->id)
        ->and($items[0]['target_count'])->toBe(4);
});

it('blocks managers from the employee target endpoints', function () {
    $org = seedOrg();

    Sanctum::actingAs($org['centerManager']);

    $this->getJson('/api/admissions/targets/summary')->assertForbidden();
    $this->getJson('/api/admissions/targets')->assertForbidden();
});
