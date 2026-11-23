<?php

use App\Enums\FieldActivityType;
use App\Filament\Resources\TeamActivities\Pages\ListTeamActivities;
use App\Filament\Resources\TeamActivities\Pages\ViewTeamActivity;
use App\Filament\Resources\TeamActivities\TeamActivityResource;
use App\Models\FieldActivity;
use App\Models\User;
use App\Services\AdminCenterContext;
use App\Support\AttendanceCalendar;
use Livewire\Livewire;

function activityFor(array $overrides = []): FieldActivity
{
    return FieldActivity::query()->create(array_merge([
        'activity_type' => FieldActivityType::VillageVisit->value,
        'activity_name' => 'Village Visit',
        'activity_at' => AttendanceCalendar::today()->setTime(9, 0),
        'remarks' => null,
        'photo_path' => 'field-activities/today.jpg',
        'latitude' => 19.076,
        'longitude' => 72.8777,
        'location' => 'Andheri',
    ], $overrides));
}

it('lists only the selected center employees and their activity records', function () {
    $org = seedOrg();
    $today = AttendanceCalendar::today();
    $yesterday = $today->copy()->subDay();
    $custom = $today->copy()->subDays(3);

    activityFor([
        'employee_id' => $org['empA']->id,
        'user_id' => $org['userA']->id,
        'center_id' => $org['centerA']->id,
        'scheme_id' => $org['projectA']->id,
        'activity_at' => $today->copy()->setTime(9, 15),
        'remarks' => 'Morning note',
    ]);
    activityFor([
        'employee_id' => $org['empA']->id,
        'user_id' => $org['userA']->id,
        'center_id' => $org['centerA']->id,
        'scheme_id' => $org['projectA']->id,
        'activity_type' => FieldActivityType::FollowUp->value,
        'activity_name' => 'Follow-up',
        'activity_at' => $today->copy()->setTime(16, 45),
        'remarks' => 'Afternoon note',
        'photo_path' => 'field-activities/later.jpg',
    ]);
    activityFor([
        'employee_id' => $org['empA']->id,
        'user_id' => $org['userA']->id,
        'center_id' => $org['centerA']->id,
        'scheme_id' => $org['projectA']->id,
        'activity_at' => $yesterday->copy()->setTime(11, 0),
        'remarks' => 'Yesterday note',
    ]);
    activityFor([
        'employee_id' => $org['empA']->id,
        'user_id' => $org['userA']->id,
        'center_id' => $org['centerB']->id,
        'scheme_id' => $org['projectB']->id,
        'activity_at' => $today->copy()->setTime(12, 0),
        'remarks' => 'Other center note',
    ]);
    activityFor([
        'employee_id' => $org['empA']->id,
        'user_id' => $org['userA']->id,
        'center_id' => $org['centerA']->id,
        'scheme_id' => $org['projectA']->id,
        'activity_at' => $custom->copy()->setTime(10, 5),
        'remarks' => 'Custom date note',
        'activity_name' => 'School meeting',
        'activity_type' => FieldActivityType::CommunityMeeting->value,
    ]);
    activityFor([
        'employee_id' => $org['empB']->id,
        'center_id' => $org['centerB']->id,
        'scheme_id' => $org['projectB']->id,
        'activity_at' => $today->copy()->setTime(8, 0),
        'remarks' => 'Center B note',
    ]);

    $this->actingAs($org['admin'])->get('/admin/team-activities')->assertRedirect('/admin/centers');

    $this->withSession([AdminCenterContext::SESSION_KEY => $org['centerA']->id]);

    Livewire::actingAs($org['admin'])
        ->test(ListTeamActivities::class)
        ->assertCanSeeTableRecords([$org['empA']])
        ->assertCanNotSeeTableRecords([$org['empB']]);

    Livewire::actingAs($org['centerManager'])
        ->test(ListTeamActivities::class)
        ->assertCanSeeTableRecords([$org['empA']])
        ->assertCanNotSeeTableRecords([$org['empB']]);

    $coordinator = User::query()->create([
        'name' => 'Activity Coordinator',
        'email' => 'activity-coord@test.local',
        'login_id' => 'activitycoord',
        'password' => 'secret',
        'role' => 'center_coordinator',
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $coordinator->coordinatedCenters()->attach($org['centerA']->id);

    Livewire::actingAs($coordinator)
        ->test(ListTeamActivities::class)
        ->assertCanSeeTableRecords([$org['empA']])
        ->assertCanNotSeeTableRecords([$org['empB']]);

    Livewire::actingAs($org['admin'])
        ->test(ViewTeamActivity::class, ['record' => $org['empA']->id])
        ->assertSee('Morning note')
        ->assertSee('Afternoon note')
        ->assertSee('Village Visit')
        ->assertSee('Follow-up')
        ->assertSee('field-activities/today.jpg')
        ->assertSee('google.com/maps?q=')
        ->assertSee('Andheri')
        ->assertSeeInOrder(['Morning note', 'Afternoon note'])
        ->assertDontSee('Yesterday note')
        ->assertDontSee('Other center note')
        ->assertDontSee('Center B note')
        ->assertDontSee('No Activity Found')
        ->assertDontSee('Punch In')
        ->call('showPeriod', 'yesterday')
        ->assertSee('Yesterday note')
        ->assertDontSee('Morning note')
        ->set('on', $custom->toDateString())
        ->assertSee('Custom date note')
        ->assertSee('School meeting')
        ->assertSee('Community Meeting')
        ->assertDontSee('Yesterday note')
        ->set('on', $today->copy()->subDays(10)->toDateString())
        ->assertSee('No Activity Found');

    $this->actingAs($org['admin'])
        ->get('/admin/team-activities/'.$org['empB']->id)
        ->assertNotFound();

    $this->actingAs($org['userA']);
    expect(TeamActivityResource::canAccess())->toBeFalse();
});
