<?php

use App\Enums\LeaveStatus;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Services\Attendance\AttendanceStatusCalculator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function leavePayload(array $overrides = []): array
{
    return array_merge([
        'leave_type' => 'casual',
        'from_date' => '2026-09-20',
        'to_date' => '2026-09-22',
        'reason' => 'Family function',
    ], $overrides);
}

it('lets an employee apply leave and auto-calculates total days', function () {
    $org = seedOrg();

    $this->actingAs($org['userA'], 'sanctum')
        ->postJson('/api/leaves', leavePayload())
        ->assertCreated()
        ->assertJsonPath('data.total_days', 3)
        ->assertJsonPath('data.status', 'pending');
});

it('prevents overlapping pending or approved leave for the same employee', function () {
    $org = seedOrg();

    $this->actingAs($org['userA'], 'sanctum')
        ->postJson('/api/leaves', leavePayload())
        ->assertCreated();

    $this->actingAs($org['userA'], 'sanctum')
        ->postJson('/api/leaves', leavePayload([
            'from_date' => '2026-09-22',
            'to_date' => '2026-09-23',
            'reason' => 'Overlap',
        ]))
        ->assertStatus(422);
});

it('lets an employee edit and cancel only pending leave', function () {
    $org = seedOrg();

    $id = $this->actingAs($org['userA'], 'sanctum')
        ->postJson('/api/leaves', leavePayload())
        ->json('data.id');

    $this->actingAs($org['userA'], 'sanctum')
        ->patchJson('/api/leaves/'.$id, leavePayload([
            'to_date' => '2026-09-21',
            'reason' => 'Updated reason',
        ]))
        ->assertOk()
        ->assertJsonPath('data.total_days', 2);

    $this->actingAs($org['userA'], 'sanctum')
        ->deleteJson('/api/leaves/'.$id)
        ->assertOk();

    expect(LeaveRequest::query()->find($id)?->status)->toBe(LeaveStatus::Cancelled);
});

it('lets the assigned center manager approve leave and marks attendance as On Leave', function () {
    Storage::fake('local');
    $org = seedOrg();

    $id = $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/leaves', array_merge(leavePayload(), [
            'document' => UploadedFile::fake()->image('note.jpg'),
        ]), ['Accept' => 'application/json'])
        ->assertCreated()
        ->json('data.id');

    Sanctum::actingAs($org['centerManager']);
    $this->postJson('/api/manager/leaves/'.$id.'/approve', [
        'approval_remark' => 'Approved for the event',
    ])->assertOk()->assertJsonPath('data.status', 'approved');

    $dates = ['2026-09-20', '2026-09-21', '2026-09-22'];
    foreach ($dates as $date) {
        $attendance = Attendance::query()
            ->where('employee_id', $org['empA']->id)
            ->whereDate('attendance_date', $date)
            ->first();
        expect($attendance)->not->toBeNull()
            ->and($attendance->attendance_status)->toBe(AttendanceStatusCalculator::STATUS_LEAVE);
    }

    $this->actingAs($org['userA'], 'sanctum')
        ->patchJson('/api/leaves/'.$id, leavePayload(['reason' => 'Too late']))
        ->assertStatus(422);
});

it('requires a rejection remark and keeps other center managers from acting', function () {
    $org = seedOrg();

    $id = $this->actingAs($org['userA'], 'sanctum')
        ->postJson('/api/leaves', leavePayload())
        ->json('data.id');

    Sanctum::actingAs($org['centerManager']);
    $this->postJson('/api/manager/leaves/'.$id.'/reject', [])
        ->assertStatus(422);

    $otherUser = \App\Models\User::query()->where('login_id', '9000000002')->firstOrFail();
    $otherId = $this->actingAs($otherUser, 'sanctum')
        ->postJson('/api/leaves', leavePayload())
        ->json('data.id');

    Sanctum::actingAs($org['centerManager']);
    $this->getJson('/api/manager/leaves/'.$otherId)->assertForbidden();
    $this->postJson('/api/manager/leaves/'.$otherId.'/approve')->assertForbidden();
});

it('lets project head view assigned project leave but not approve, director view assigned project, and admin view all', function () {
    $org = seedOrg();

    $ownId = $this->actingAs($org['userA'], 'sanctum')
        ->postJson('/api/leaves', leavePayload())
        ->json('data.id');

    $otherUser = \App\Models\User::query()->where('login_id', '9000000002')->firstOrFail();
    $otherId = $this->actingAs($otherUser, 'sanctum')
        ->postJson('/api/leaves', leavePayload(['reason' => 'Other project']))
        ->json('data.id');

    Sanctum::actingAs($org['projectHead']);
    $this->getJson('/api/manager/leaves/'.$ownId)->assertOk();
    $this->getJson('/api/manager/leaves/'.$otherId)->assertForbidden();
    $this->postJson('/api/manager/leaves/'.$ownId.'/approve')->assertForbidden();

    Sanctum::actingAs($org['director']);
    $this->getJson('/api/director/leaves/'.$ownId)->assertOk();
    $this->getJson('/api/director/leaves/'.$otherId)->assertForbidden();
    $this->postJson('/api/director/leaves/'.$ownId.'/approve')->assertNotFound();

    Sanctum::actingAs($org['admin']);
    $this->getJson('/api/director/leaves/'.$ownId)->assertOk();
    $this->getJson('/api/director/leaves/'.$otherId)->assertOk();
    $this->postJson('/api/director/leaves/'.$ownId.'/approve')->assertNotFound();
});

it('keeps punch in working after leave tables exist', function () {
    Storage::fake('public');
    $org = seedOrg();

    $this->actingAs($org['userA'], 'sanctum')
        ->post('/api/attendance/punch-in', [
            'latitude' => 18.52,
            'longitude' => 73.85,
            'location_address' => 'Pune',
            'photo' => UploadedFile::fake()->image('punch.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertCreated();
});
