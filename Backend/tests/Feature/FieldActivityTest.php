<?php

use App\Enums\FieldActivityType;
use App\Models\FieldActivity;
use App\Support\AttendanceCalendar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function fieldActivityPayload(array $overrides = []): array
{
    return array_merge([
        'activity_type' => FieldActivityType::VillageVisit->value,
        'activity_name' => 'Village Visit',
        'remarks' => 'Met local leaders',
        'latitude' => 18.5204,
        'longitude' => 73.8567,
        'location' => 'Pune, Maharashtra',
        'photo' => UploadedFile::fake()->image('activity.jpg'),
    ], $overrides);
}

it('lets an employee submit a field activity with photo and gps', function () {
    Storage::fake('public');
    $org = seedOrg();

    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/field-activities', fieldActivityPayload(), ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.activity_type', 'village_visit')
        ->assertJsonPath('data.employee_id', $org['empA']->id)
        ->assertJsonPath('data.center_id', $org['centerA']->id)
        ->assertJsonPath('data.location', 'Pune, Maharashtra');

    expect(FieldActivity::query()->where('employee_id', $org['empA']->id)->count())->toBe(1);
});

it('requires a camera photo when submitting a field activity', function () {
    Storage::fake('public');
    $org = seedOrg();

    $this->actingAs($org['userA'], 'sanctum')
        ->postJson('/api/field-activities', [
            'activity_type' => FieldActivityType::VillageVisit->value,
            'latitude' => 18.52,
            'longitude' => 73.85,
            'location' => 'Pune',
        ])
        ->assertStatus(422);
});

it('lets an employee list and view only their own field activities', function () {
    Storage::fake('public');
    $org = seedOrg();
    $otherUser = \App\Models\User::query()->where('login_id', '9000000002')->firstOrFail();

    $ownId = $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/field-activities', fieldActivityPayload(), ['Accept' => 'application/json'])
        ->json('data.id');

    $otherId = $this->actingAs($otherUser, 'sanctum')
        ->post('/api/field-activities', fieldActivityPayload([
            'activity_name' => 'Other village',
            'location' => 'Nashik',
        ]), ['Accept' => 'application/json'])
        ->json('data.id');

    $this->actingAs($org['userA'], 'sanctum')
        ->getJson('/api/field-activities')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ownId);

    $this->actingAs($org['userA'], 'sanctum')
        ->getJson('/api/field-activities/'.$otherId)
        ->assertForbidden();
});

it('lets center manager view assigned-center activities and filter by employee', function () {
    Storage::fake('public');
    $org = seedOrg();
    $otherUser = \App\Models\User::query()->where('login_id', '9000000002')->firstOrFail();

    $ownId = $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/field-activities', fieldActivityPayload(), ['Accept' => 'application/json'])
        ->json('data.id');

    $otherId = $this->actingAs($otherUser, 'sanctum')
        ->post('/api/field-activities', fieldActivityPayload([
            'location' => 'Nashik',
        ]), ['Accept' => 'application/json'])
        ->json('data.id');

    Sanctum::actingAs($org['centerManager']);
    $this->getJson('/api/manager/field-activities')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ownId)
        ->assertJsonPath('data.0.employee_name', 'Emp A');

    $this->getJson('/api/manager/field-activities/'.$ownId)->assertOk();
    $this->getJson('/api/manager/field-activities/'.$otherId)->assertForbidden();

    $this->getJson('/api/manager/field-activities?employee_id='.$org['empA']->id)
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/manager/field-activities?employee_id='.$org['empB']->id)
        ->assertForbidden();
});

it('lets project head view assigned project activities and director view all', function () {
    Storage::fake('public');
    $org = seedOrg();
    $otherUser = \App\Models\User::query()->where('login_id', '9000000002')->firstOrFail();

    $ownId = $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/field-activities', fieldActivityPayload(), ['Accept' => 'application/json'])
        ->json('data.id');

    $otherId = $this->actingAs($otherUser, 'sanctum')
        ->post('/api/field-activities', fieldActivityPayload([
            'location' => 'Nashik',
        ]), ['Accept' => 'application/json'])
        ->json('data.id');

    Sanctum::actingAs($org['projectHead']);
    $this->getJson('/api/manager/field-activities/'.$ownId)->assertOk();
    $this->getJson('/api/manager/field-activities/'.$otherId)->assertForbidden();

    Sanctum::actingAs($org['director']);
    $this->getJson('/api/director/field-activities')
        ->assertOk()
        ->assertJsonCount(2, 'data');
    $this->getJson('/api/director/field-activities/'.$ownId)->assertOk();
    $this->getJson('/api/director/field-activities/'.$otherId)->assertOk();
});

it('filters supervisor field activities by today week month and custom date', function () {
    Storage::fake('public');
    $org = seedOrg();
    $today = AttendanceCalendar::today();

    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/field-activities', fieldActivityPayload(), ['Accept' => 'application/json'])
        ->assertCreated();

    FieldActivity::query()->create([
        'employee_id' => $org['empA']->id,
        'user_id' => $org['userA']->id,
        'center_id' => $org['centerA']->id,
        'scheme_id' => $org['projectA']->id,
        'activity_type' => FieldActivityType::FollowUp,
        'activity_name' => 'Last month follow-up',
        'activity_at' => $today->copy()->subMonth()->startOfMonth()->addDays(2)->setTime(11, 0),
        'photo_path' => 'field-activities/old.jpg',
        'latitude' => 18.52,
        'longitude' => 73.85,
        'location' => 'Pune',
    ]);

    Sanctum::actingAs($org['centerManager']);
    $this->getJson('/api/manager/field-activities?period=today')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/manager/field-activities?period=this_week')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/manager/field-activities?period=this_month')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $from = $today->copy()->subMonth()->startOfMonth()->toDateString();
    $to = $today->copy()->subMonth()->endOfMonth()->toDateString();
    $this->getJson('/api/manager/field-activities?period=custom&date_from='.$from.'&date_to='.$to)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.activity_name', 'Last month follow-up');
});

it('adds field activities today count to dashboard without changing other keys', function () {
    Storage::fake('public');
    $org = seedOrg();

    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/field-activities', fieldActivityPayload(), ['Accept' => 'application/json'])
        ->assertCreated();

    Sanctum::actingAs($org['centerManager']);
    $cm = $this->getJson('/api/dashboard')->assertOk()->json('data');
    expect($cm['field_activities_today'])->toBe(1)
        ->and($cm['employees'])->toBe(1)
        ->and($cm['punched_in_today'])->toBe(0);

    Sanctum::actingAs($org['director']);
    $director = $this->getJson('/api/dashboard')->assertOk()->json('data');
    expect($director['field_activities_today'])->toBe(1);
});
