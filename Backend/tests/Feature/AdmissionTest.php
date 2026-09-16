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

    $submitted = Admission::query()->findOrFail($id);
    expect($submitted->submitted_at)->not->toBeNull();

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

function submitCompleteAdmission(array $ctx, array $overrides = []): int
{
    $id = test()->actingAs($ctx['userA'], 'sanctum')
        ->postJson('/api/admissions/drafts', admissionPayload($ctx, $overrides))
        ->json('data.id');

    test()->actingAs($ctx['userA'], 'sanctum')
        ->post('/api/admissions/'.$id.'/documents', [
            'document_type' => AdmissionDocumentType::Aadhaar->value,
            'file' => UploadedFile::fake()->create('aadhaar.pdf', 80, 'application/pdf'),
        ], ['Accept' => 'application/json'])
        ->assertOk();

    test()->actingAs($ctx['userA'], 'sanctum')
        ->postJson('/api/admissions/'.$id.'/submit')
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted');

    return $id;
}

it('lets the assigned center manager confirm a submitted admission', function () {
    Storage::fake('local');
    $ctx = seedAdmissionsContext();
    $id = submitCompleteAdmission($ctx);

    Sanctum::actingAs($ctx['centerManager']);
    $this->postJson('/api/manager/admissions/'.$id.'/confirm')
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.confirmed_by', $ctx['centerManager']->id)
        ->assertJsonPath('data.reviewed_by_user_id', $ctx['centerManager']->id);

    $confirmed = Admission::query()->find($id);
    expect($confirmed->confirmed_at)->not->toBeNull()
        ->and($confirmed->reviewed_by_user_id)->toBe($ctx['centerManager']->id);
});

it('requires a reason to revert and lets the employee edit and resubmit', function () {
    Storage::fake('local');
    $ctx = seedAdmissionsContext();
    $id = submitCompleteAdmission($ctx);

    Sanctum::actingAs($ctx['centerManager']);
    $this->postJson('/api/manager/admissions/'.$id.'/revert')
        ->assertStatus(422);

    $this->postJson('/api/manager/admissions/'.$id.'/revert', [
        'reason' => 'Village name is incomplete.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'reverted')
        ->assertJsonPath('data.editable', true)
        ->assertJsonPath('data.review_reason', 'Village name is incomplete.');

    $this->actingAs($ctx['userA'], 'sanctum')
        ->getJson('/api/admissions/drafts')
        ->assertOk()
        ->assertJsonFragment(['id' => $id, 'status' => 'reverted', 'review_reason' => 'Village name is incomplete.']);

    $this->actingAs($ctx['userA'], 'sanctum')
        ->patchJson('/api/admissions/drafts/'.$id, ['village' => 'Kharadi'])
        ->assertOk()
        ->assertJsonPath('data.village', 'Kharadi');

    $this->actingAs($ctx['userA'], 'sanctum')
        ->postJson('/api/admissions/'.$id.'/submit')
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.review_reason', null);
});

it('rejects a submitted admission with a mandatory reason', function () {
    Storage::fake('local');
    $ctx = seedAdmissionsContext();
    $id = submitCompleteAdmission($ctx);

    Sanctum::actingAs($ctx['centerManager']);
    $this->postJson('/api/manager/admissions/'.$id.'/reject', [
        'reason' => 'Documents are not readable.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.editable', false);

    $this->actingAs($ctx['userA'], 'sanctum')
        ->getJson('/api/admissions/submitted')
        ->assertOk()
        ->assertJsonFragment(['status' => 'rejected', 'review_reason' => 'Documents are not readable.']);

    $this->actingAs($ctx['userA'], 'sanctum')
        ->postJson('/api/admissions/'.$id.'/submit')
        ->assertStatus(422);
});

it('keeps drafts out of review and scopes manager admissions to assigned centers', function () {
    Storage::fake('local');
    $ctx = seedAdmissionsContext();
    $draftId = $this->actingAs($ctx['userA'], 'sanctum')
        ->postJson('/api/admissions/drafts', admissionPayload($ctx, ['first_name' => 'DraftOnly']))
        ->json('data.id');
    $submittedId = submitCompleteAdmission($ctx, ['first_name' => 'Ready']);

    Sanctum::actingAs($ctx['centerManager']);
    $this->postJson('/api/manager/admissions/'.$draftId.'/confirm')->assertStatus(403);

    $summary = $this->getJson('/api/manager/admissions/summary')->assertOk()->json('data');
    expect($summary['draft'])->toBeGreaterThanOrEqual(1)
        ->and($summary['submitted'])->toBeGreaterThanOrEqual(1)
        ->and($summary)->toHaveKeys(['submitted', 'confirmed', 'draft', 'reverted', 'rejected', 'total'])
        ->and($summary['total'])->toBe(
            (int) $summary['submitted']
            + (int) $summary['confirmed']
            + (int) $summary['draft']
            + (int) $summary['reverted']
            + (int) $summary['rejected']
        );

    $dashboard = $this->getJson('/api/dashboard')->assertOk()->json('data');
    expect($dashboard['admission_counts']['submitted'])->toBe($summary['submitted'])
        ->and($dashboard['admission_counts']['draft'])->toBe($summary['draft'])
        ->and($dashboard['admissions'])->toBe($summary['submitted']);

    $submitted = $this->getJson('/api/manager/admissions?status=submitted')
        ->assertOk()
        ->json('data');
    expect(collect($submitted)->pluck('id'))->toContain($submittedId)
        ->and(collect($submitted)->pluck('id'))->not->toContain($draftId)
        ->and(collect($submitted)->pluck('status')->unique()->all())->toBe(['submitted']);

    $this->getJson('/api/manager/admissions/'.$submittedId)
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.can_confirm', true)
        ->assertJsonPath('data.can_revert', true)
        ->assertJsonPath('data.can_reject', true);

    $otherUser = \App\Models\User::query()->where('login_id', '9000000002')->firstOrFail();
    $hiddenId = $this->actingAs($otherUser, 'sanctum')
        ->postJson('/api/admissions/drafts', admissionPayload($ctx, ['first_name' => 'Hidden']))
        ->json('data.id');

    Sanctum::actingAs($ctx['centerManager']);
    $this->getJson('/api/manager/admissions/'.$hiddenId)->assertForbidden();
    $this->postJson('/api/manager/admissions/'.$hiddenId.'/confirm')->assertForbidden();
});

it('exposes only the signed-in employee admission counts', function () {
    Storage::fake('local');
    $ctx = seedAdmissionsContext();
    $this->actingAs($ctx['userA'], 'sanctum')
        ->postJson('/api/admissions/drafts', admissionPayload($ctx, ['first_name' => 'Mine']));
    $submittedId = submitCompleteAdmission($ctx, ['first_name' => 'Ready']);

    $otherUser = \App\Models\User::query()->where('login_id', '9000000002')->firstOrFail();
    $this->actingAs($otherUser, 'sanctum')
        ->postJson('/api/admissions/drafts', admissionPayload($ctx, ['first_name' => 'Other']));

    $summary = $this->actingAs($ctx['userA'], 'sanctum')
        ->getJson('/api/admissions/summary')
        ->assertOk()
        ->json('data');

    expect($summary['draft'])->toBe(1)
        ->and($summary['submitted'])->toBe(1)
        ->and($summary['confirmed'])->toBe(0)
        ->and($summary['reverted'])->toBe(0)
        ->and($summary['rejected'])->toBe(0)
        ->and($summary['total'])->toBe(2);

    Sanctum::actingAs($ctx['centerManager']);
    $this->postJson('/api/manager/admissions/'.$submittedId.'/confirm')->assertOk();

    $after = $this->actingAs($ctx['userA'], 'sanctum')
        ->getJson('/api/admissions/summary')
        ->assertOk()
        ->json('data');

    expect($after['submitted'])->toBe(0)
        ->and($after['confirmed'])->toBe(1)
        ->and($after['draft'])->toBe(1)
        ->and($after['total'])->toBe(2);

    $this->actingAs($ctx['centerManager'], 'sanctum')
        ->getJson('/api/admissions/summary')
        ->assertForbidden();
});

it('counts only confirmed admissions toward employee target achievement', function () {
    Storage::fake('local');
    $ctx = seedAdmissionsContext();
    [$start] = \App\Support\AdmissionTargetPeriod::resolve('this_week');

    \App\Models\AdmissionTarget::create([
        'employee_id' => $ctx['empA']->id,
        'center_id' => $ctx['centerA']->id,
        'scheme_id' => $ctx['projectA']->id,
        'target_type' => \App\Enums\AdmissionTargetType::Weekly,
        'period_start' => $start->toDateString(),
        'period_end' => $start->copy()->endOfWeek()->toDateString(),
        'target_count' => 5,
    ]);

    $submittedId = submitCompleteAdmission($ctx, ['first_name' => 'Pending']);
    Admission::query()->whereKey($submittedId)->update([
        'submitted_at' => $start->copy()->addDay(),
    ]);

    $this->actingAs($ctx['userA'], 'sanctum')
        ->getJson('/api/admissions/targets/summary?preset=this_week')
        ->assertOk()
        ->assertJsonPath('data.achieved', 0);

    Sanctum::actingAs($ctx['centerManager']);
    $this->postJson('/api/manager/admissions/'.$submittedId.'/confirm')->assertOk();

    $this->actingAs($ctx['userA'], 'sanctum')
        ->getJson('/api/admissions/targets/summary?preset=this_week')
        ->assertOk()
        ->assertJsonPath('data.achieved', 1);
});

