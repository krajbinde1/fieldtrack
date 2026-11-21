<?php

use App\Filament\Resources\Centers\Pages\CreateCenter;
use App\Filament\Resources\Centers\Pages\EditCenter;
use App\Filament\Resources\Centers\Pages\ListCenters;
use App\Filament\Resources\Schemes\SchemeResource;
use App\Models\Center;
use App\Models\Scheme;
use App\Support\CenterCodeGenerator;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Select;
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

it('hides scheme master from navigation and keeps the scheme resource available', function () {
    expect(SchemeResource::shouldRegisterNavigation())->toBeFalse()
        ->and(SchemeResource::getPages())->toHaveKeys(['index', 'create', 'edit']);

    $this->actingAs(seedOrg()['admin'])
        ->get('/admin/schemes')
        ->assertOk();
});

it('creates a scheme from the center form and selects it without clearing entered data', function () {
    $org = seedOrg();

    $component = Livewire::actingAs($org['admin'])
        ->test(CreateCenter::class)
        ->assertFormFieldExists('scheme_id', function (Select $field): bool {
            return $field->isSearchable() && $field->getLabel() === 'Select Scheme';
        })
        ->assertActionHasLabel(
            TestAction::make('createOption')->schemaComponent('scheme_id'),
            '+ Create New Scheme',
        )
        ->fillForm([
            'name' => 'Center With New Scheme',
            'address' => '12 Field Road',
            'is_active' => true,
        ])
        ->callAction(TestAction::make('createOption')->schemaComponent('scheme_id'), [
            'name' => 'Inline Scheme',
            'code' => 'INS',
            'description' => 'Created from center',
            'is_active' => true,
        ])
        ->assertHasNoActionErrors()
        ->assertSet('data.name', 'Center With New Scheme')
        ->assertSet('data.address', '12 Field Road');

    $scheme = Scheme::query()->where('code', 'INS')->first();
    expect($scheme)->not->toBeNull()
        ->and($scheme->name)->toBe('Inline Scheme')
        ->and($scheme->description)->toBe('Created from center')
        ->and($scheme->is_active)->toBeTrue();

    $component
        ->assertSet('data.scheme_id', $scheme->id)
        ->call('create')
        ->assertHasNoFormErrors();

    $center = Center::query()->where('name', 'Center With New Scheme')->firstOrFail();
    expect($center->scheme_id)->toBe($scheme->id)
        ->and($center->address)->toBe('12 Field Road')
        ->and($org['projectA']->fresh())->not->toBeNull()
        ->and($org['centerA']->fresh()->scheme_id)->toBe($org['projectA']->id);

    Livewire::actingAs($org['projectHead'])
        ->test(CreateCenter::class)
        ->assertActionHidden(TestAction::make('createOption')->schemaComponent('scheme_id'));

    Livewire::actingAs($org['admin'])
        ->test(EditCenter::class, ['record' => $org['centerA']->getKey()])
        ->fillForm([
            'name' => 'Center A Kept',
            'address' => 'Kept Address',
            'centerManagers' => [$org['centerManager']->id],
        ])
        ->callAction(TestAction::make('createOption')->schemaComponent('scheme_id'), [
            'name' => 'Edit Inline Scheme',
            'code' => 'EIS',
            'description' => null,
            'is_active' => true,
        ])
        ->assertHasNoActionErrors()
        ->assertSet('data.name', 'Center A Kept')
        ->assertSet('data.address', 'Kept Address')
        ->call('save')
        ->assertHasNoFormErrors();

    $editedScheme = Scheme::query()->where('code', 'EIS')->firstOrFail();
    expect($org['centerA']->fresh()->scheme_id)->toBe($editedScheme->id)
        ->and($org['centerA']->fresh()->name)->toBe('Center A Kept')
        ->and($org['centerA']->fresh()->address)->toBe('Kept Address')
        ->and($org['centerB']->fresh()->scheme_id)->toBe($org['projectB']->id)
        ->and($org['centerA']->fresh()->centerManagers->pluck('id')->all())->toContain($org['centerManager']->id);
});
