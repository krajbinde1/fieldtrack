<?php

use App\Filament\Resources\Centers\Pages\CreateCenter;
use App\Filament\Resources\Centers\Pages\EditCenter;
use App\Filament\Resources\Centers\Pages\ListCenters;
use App\Models\Center;
use App\Support\CenterCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('generates unique CTR center codes and does not change existing codes', function () {
    $org = seedOrg();

    expect($org['centerA']->fresh()->code)->toBe('CA')
        ->and($org['centerB']->fresh()->code)->toBe('CB')
        ->and(CenterCodeGenerator::generateNext())->toBe('CTR001');

    $first = Center::query()->create([
        'scheme_id' => $org['projectA']->id,
        'name' => 'Auto Center One',
        'is_active' => true,
    ]);
    $second = Center::query()->create([
        'scheme_id' => $org['projectB']->id,
        'name' => 'Auto Center Two',
        'is_active' => true,
    ]);

    expect($first->code)->toBe('CTR001')
        ->and($second->code)->toBe('CTR002')
        ->and($org['centerA']->fresh()->code)->toBe('CA')
        ->and($org['centerB']->fresh()->code)->toBe('CB');

    $first->update(['code' => 'HACKED', 'name' => 'Auto Center One Updated']);
    expect($first->fresh()->code)->toBe('CTR001')
        ->and($first->fresh()->name)->toBe('Auto Center One Updated');
});

it('hides center code on create/edit and shows it on the center list', function () {
    $org = seedOrg();

    Livewire::actingAs($org['admin'])
        ->test(CreateCenter::class)
        ->assertFormFieldDoesNotExist('code')
        ->fillForm([
            'scheme_id' => $org['projectA']->id,
            'name' => 'Filament Auto Center',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = Center::query()->where('name', 'Filament Auto Center')->first();
    expect($created)->not->toBeNull()
        ->and($created->code)->toBe('CTR001');

    Livewire::actingAs($org['admin'])
        ->test(EditCenter::class, ['record' => $created->getKey()])
        ->assertFormFieldDoesNotExist('code')
        ->fillForm([
            'name' => 'Filament Auto Center Renamed',
            'code' => 'MANUAL',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($created->fresh()->name)->toBe('Filament Auto Center Renamed')
        ->and($created->fresh()->code)->toBe('CTR001');

    Livewire::actingAs($org['admin'])
        ->test(ListCenters::class)
        ->assertCanSeeTableRecords([$created, $org['centerA']])
        ->assertSee('CTR001')
        ->assertSee('CA');
});
