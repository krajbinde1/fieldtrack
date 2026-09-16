<?php

use App\Enums\AdmissionDocumentType;
use App\Enums\AdmissionStatus;
use App\Models\Admission;
use App\Models\MaharashtraDistrict;
use App\Models\MaharashtraTaluka;
use App\Models\Scheme;
use Database\Seeders\MaharashtraGeoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function seedAdmissionsContext(): array
{
    $org = seedOrg();
    (new MaharashtraGeoSeeder)->run();

    $activeScheme = Scheme::query()->create([
        'name' => 'Skill Development',
        'code' => 'SKILL',
        'is_active' => true,
    ]);
    $inactiveScheme = Scheme::query()->create([
        'name' => 'Closed Scheme',
        'code' => 'CLOSED',
        'is_active' => false,
    ]);

    $pune = MaharashtraDistrict::query()->where('code', 'PUNE')->firstOrFail();
    $haveli = MaharashtraTaluka::query()->where('district_id', $pune->id)->where('code', 'HAVELI')->firstOrFail();
    $nashik = MaharashtraDistrict::query()->where('code', 'NASHIK')->firstOrFail();

    return array_merge($org, compact('activeScheme', 'inactiveScheme', 'pune', 'haveli', 'nashik'));
}

function admissionPayload(array $ctx, array $overrides = []): array
{
    return array_merge([
        'scheme_id' => $ctx['activeScheme']->id,
        'first_name' => 'Ravi',
        'middle_name' => 'Kumar',
        'last_name' => 'Patil',
        'gender' => 'Male',
        'religion' => 'Hindu',
        'caste' => 'OBC',
        'district_id' => $ctx['pune']->id,
        'taluka_id' => $ctx['haveli']->id,
        'village' => 'Wagholi',
        'current_step' => 3,
    ], $overrides);
}

it('lists only active schemes to employees and hides inactive ones', function () {
    $ctx = seedAdmissionsContext();

    $this->actingAs($ctx['userA'], 'sanctum')
        ->getJson('/api/admissions/schemes')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Skill Development'])
        ->assertJsonMissing(['name' => 'Closed Scheme']);
});

it('returns every Maharashtra district and keeps Maharashtra as the state', function () {
    $org = seedOrg();

    $response = $this->actingAs($org['userA'], 'sanctum')
        ->getJson('/api/admissions/districts')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.state', 'Maharashtra');

    $districts = collect($response->json('data.districts'));
    expect($districts)->toHaveCount(\App\Support\MaharashtraGeoCatalog::districtCount())
        ->and($districts->pluck('name'))->toContain('Pune')
        ->and($districts->pluck('name'))->toContain('Nashik')
        ->and($districts->pluck('name'))->toContain('Chhatrapati Sambhajinagar')
        ->and(\App\Models\MaharashtraDistrict::query()->count())->toBe(\App\Support\MaharashtraGeoCatalog::districtCount())
        ->and(\App\Models\MaharashtraTaluka::query()->count())->toBe(\App\Support\MaharashtraGeoCatalog::talukaCount());
});

it('seeds district master data without duplicates when the lookup table is empty', function () {
    $org = seedOrg();
    (new MaharashtraGeoSeeder)->run();
    (new MaharashtraGeoSeeder)->run();

    expect(\App\Models\MaharashtraDistrict::query()->count())->toBe(\App\Support\MaharashtraGeoCatalog::districtCount())
        ->and(\App\Models\MaharashtraTaluka::query()->count())->toBe(\App\Support\MaharashtraGeoCatalog::talukaCount());

    $this->actingAs($org['userA'], 'sanctum')
        ->getJson('/api/admissions/districts')
        ->assertOk()
        ->assertJsonPath('data.state', 'Maharashtra');
});

it('returns talukas only for the selected district', function () {
    $ctx = seedAdmissionsContext();

    $puneTalukas = $this->actingAs($ctx['userA'], 'sanctum')
        ->getJson('/api/admissions/districts/'.$ctx['pune']->id.'/talukas')
        ->assertOk()
        ->json('data.talukas');

    $names = collect($puneTalukas)->pluck('name');
    expect($names)->toContain('Haveli')
        ->and($names)->not->toContain('Nashik')
        ->and($names)->not->toContain('Igatpuri');
});

it('saves incomplete drafts without required-field validation', function () {
    $ctx = seedAdmissionsContext();

    $response = $this->actingAs($ctx['userA'], 'sanctum')
        ->postJson('/api/admissions/drafts', [
            'first_name' => 'Anita',
            'current_step' => 2,
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.first_name', 'Anita');

    $id = $response->json('data.id');

    $this->actingAs($ctx['userA'], 'sanctum')
        ->patchJson('/api/admissions/drafts/'.$id, [
            'last_name' => 'Shinde',
            'gender' => 'Female',
            'current_step' => 2,
        ])
        ->assertOk()
        ->assertJsonPath('data.last_name', 'Shinde')
        ->assertJsonPath('data.first_name', 'Anita');
});

it('restores a draft with previously uploaded documents', function () {
    Storage::fake('local');
    $ctx = seedAdmissionsContext();

    $id = $this->actingAs($ctx['userA'], 'sanctum')
        ->postJson('/api/admissions/drafts', admissionPayload($ctx))
        ->assertCreated()
        ->json('data.id');

    $this->actingAs($ctx['userA'], 'sanctum')
        ->post('/api/admissions/'.$id.'/documents', [
            'document_type' => AdmissionDocumentType::Aadhaar->value,
            'file' => UploadedFile::fake()->image('aadhaar.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.document.original_name', 'aadhaar.jpg');

    $show = $this->actingAs($ctx['userA'], 'sanctum')
        ->getJson('/api/admissions/'.$id)
        ->assertOk();

    expect($show->json('data.documents.0.original_name'))->toBe('aadhaar.jpg')
        ->and($show->json('data.village'))->toBe('Wagholi');
});

it('lets an employee delete their own draft and blocks submitted deletes', function () {
    Storage::fake('local');
    $ctx = seedAdmissionsContext();

    $draftId = $this->actingAs($ctx['userA'], 'sanctum')
        ->postJson('/api/admissions/drafts', admissionPayload($ctx))
        ->json('data.id');

    $this->actingAs($ctx['userA'], 'sanctum')
        ->deleteJson('/api/admissions/drafts/'.$draftId)
        ->assertOk();

    expect(Admission::query()->find($draftId))->toBeNull();
});

it('submits a complete admission and rejects a second submit', function () {
    Storage::fake('local');
    $ctx = seedAdmissionsContext();

    $id = $this->actingAs($ctx['userA'], 'sanctum')
        ->postJson('/api/admissions/drafts', admissionPayload($ctx))
        ->json('data.id');

    $this->actingAs($ctx['userA'], 'sanctum')
        ->post('/api/admissions/'.$id.'/documents', [
            'document_type' => AdmissionDocumentType::Aadhaar->value,
            'file' => UploadedFile::fake()->create('aadhaar.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json'])
        ->assertOk();

    $this->actingAs($ctx['userA'], 'sanctum')
        ->postJson('/api/admissions/'.$id.'/submit')
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted');

    $second = $this->actingAs($ctx['userA'], 'sanctum')
        ->postJson('/api/admissions/'.$id.'/submit')
        ->assertStatus(422);
    expect(json_encode($second->json()))->toContain('already submitted');

    $this->actingAs($ctx['userA'], 'sanctum')
        ->patchJson('/api/admissions/drafts/'.$id, ['village' => 'Changed'])
        ->assertStatus(422);

    $this->actingAs($ctx['userA'], 'sanctum')
        ->deleteJson('/api/admissions/drafts/'.$id)
        ->assertStatus(422);
});

it('blocks submit when required fields or aadhaar are missing', function () {
    $ctx = seedAdmissionsContext();

    $id = $this->actingAs($ctx['userA'], 'sanctum')
        ->postJson('/api/admissions/drafts', ['first_name' => 'Only'])
        ->json('data.id');

    $this->actingAs($ctx['userA'], 'sanctum')
        ->postJson('/api/admissions/'.$id.'/submit')
        ->assertStatus(422);
});

it('rejects a taluka that does not belong to the selected district', function () {
    $ctx = seedAdmissionsContext();
    $nashikTaluka = MaharashtraTaluka::query()->where('district_id', $ctx['nashik']->id)->firstOrFail();

    $this->actingAs($ctx['userA'], 'sanctum')
        ->postJson('/api/admissions/drafts', admissionPayload($ctx, [
            'district_id' => $ctx['pune']->id,
            'taluka_id' => $nashikTaluka->id,
        ]))
        ->assertStatus(422);
});

it('authorizes admission visibility by role hierarchy', function () {
    Storage::fake('local');
    $ctx = seedAdmissionsContext();

    $ownId = $this->actingAs($ctx['userA'], 'sanctum')
        ->postJson('/api/admissions/drafts', admissionPayload($ctx, ['first_name' => 'Own']))
        ->json('data.id');

    $otherUser = \App\Models\User::query()->where('login_id', '9000000002')->firstOrFail();
    $otherId = $this->actingAs($otherUser, 'sanctum')
        ->postJson('/api/admissions/drafts', admissionPayload($ctx, ['first_name' => 'Other']))
        ->json('data.id');

    $this->actingAs($ctx['userA'], 'sanctum')
        ->getJson('/api/admissions/'.$otherId)
        ->assertForbidden();

    Sanctum::actingAs($ctx['centerManager']);
    $this->getJson('/api/manager/admissions/'.$ownId)->assertOk();
    $this->getJson('/api/manager/admissions/'.$otherId)->assertForbidden();

    Sanctum::actingAs($ctx['projectHead']);
    $this->getJson('/api/manager/admissions/'.$ownId)->assertOk();
    $this->getJson('/api/manager/admissions/'.$otherId)->assertForbidden();

    Sanctum::actingAs($ctx['director']);
    $this->getJson('/api/director/admissions/'.$ownId)->assertOk();
    $this->getJson('/api/director/admissions/'.$otherId)->assertOk();

    Sanctum::actingAs($ctx['admin']);
    $this->getJson('/api/director/admissions/'.$ownId)->assertOk();
    $this->getJson('/api/director/admissions/'.$otherId)->assertOk();
});

it('serves admission documents only to authorized users and not as public files', function () {
    Storage::fake('local');
    $ctx = seedAdmissionsContext();

    $id = $this->actingAs($ctx['userA'], 'sanctum')
        ->postJson('/api/admissions/drafts', admissionPayload($ctx))
        ->json('data.id');

    $docId = $this->actingAs($ctx['userA'], 'sanctum')
        ->post('/api/admissions/'.$id.'/documents', [
            'document_type' => AdmissionDocumentType::Aadhaar->value,
            'file' => UploadedFile::fake()->image('id.png'),
        ], ['Accept' => 'application/json'])
        ->json('data.document.id');

    $admission = Admission::query()->findOrFail($id);
    $document = $admission->documents()->first();
    expect($document->path)->toStartWith('admissions/')
        ->and($document->storage_key)->not->toBe($document->original_name);

    $this->actingAs($ctx['userA'], 'sanctum')
        ->get('/api/admissions/'.$id.'/documents/'.$docId)
        ->assertOk();

    $otherUser = \App\Models\User::query()->where('login_id', '9000000002')->firstOrFail();
    $this->actingAs($otherUser, 'sanctum')
        ->get('/api/admissions/'.$id.'/documents/'.$docId)
        ->assertForbidden();

    $public = $this->get('/storage/'.$document->path);
    expect($public->status())->toBeIn([403, 404]);
});

it('keeps punch in working after admissions tables exist', function () {
    Storage::fake('public');
    seedAdmissionsContext();
    $user = \App\Models\User::query()->where('login_id', '9000000001')->firstOrFail();

    $this->actingAs($user, 'sanctum')
        ->post('/api/attendance/punch-in', [
            'latitude' => 18.52,
            'longitude' => 73.85,
            'location_address' => 'Pune',
            'photo' => UploadedFile::fake()->image('punch.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertCreated();
});
