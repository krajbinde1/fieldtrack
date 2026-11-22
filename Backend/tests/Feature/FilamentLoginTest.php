<?php

use App\Enums\UserRole;
use App\Filament\Auth\Login;
use App\Filament\Resources\Centers\Pages\CreateCenter;
use App\Filament\Resources\Centers\Pages\ListCenters;
use App\Models\Attendance;
use App\Models\Center;
use App\Models\Employee;
use App\Models\User;
use App\Services\AdminCenterContext;
use App\Support\AttendanceCalendar;
use App\Support\PublicStorage;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
});

it('lets the existing top-level login sign in as admin', function () {
    $user = User::query()->where('login_id', 'admin')->firstOrFail();
    expect($user->role)->toBe(UserRole::Admin->value);

    Livewire::test(Login::class)
        ->fillForm([
            'login_id' => 'director',
            'password' => 'Director@123',
        ])
        ->call('authenticate')
        ->assertHasFormErrors(['login_id']);

    $this->assertGuest();

    Livewire::test(Login::class)
        ->fillForm([
            'login_id' => 'admin',
            'password' => 'Director@123',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect('/admin');

    $this->assertAuthenticatedAs($user);
});

it('lets an admin open organization, people, and field-operation pages', function () {
    $admin = User::query()->where('login_id', 'admin')->firstOrFail();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Main Admin Dashboard')
        ->assertSee('Main Dashboard')
        ->assertSee('Center Dashboard')
        ->assertDontSee('Change Center');

    $this->actingAs($admin)
        ->get('/admin/center-dashboard')
        ->assertRedirect('/admin/centers');

    $this->withSession([
        AdminCenterContext::SESSION_KEY => Center::query()->where('code', 'C1')->value('id'),
    ]);

    $dashboard = $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Param FieldTrack')
        ->assertSee('Main Admin Dashboard')
        ->assertSee('Main Dashboard')
        ->assertSee('Center Dashboard')
        ->assertDontSee('Scheme Master')
        ->assertSee('Change Center')
        ->assertSee('Back to All Centers');

    $this->actingAs($admin)
        ->get('/admin/center-dashboard')
        ->assertOk()
        ->assertSee('Demo Center Dashboard')
        ->assertSee('Change Center')
        ->assertDontSee('Main Admin Dashboard');

    $html = $dashboard->getContent();
    expect(substr_count($html, 'class="ft-topbar-search"'))->toBe(0)
        ->and($html)->not->toContain('class="fi-global-search-ctn')
        ->and($html)->not->toContain('Filament\\Livewire\\GlobalSearch')
        ->and($html)->toContain('fi-page-header-main-ctn')
        ->and($html)->toContain('padding-top: 0.75rem !important');

    $users = $this->actingAs($admin)->get('/admin/users')->assertOk();
    expect(substr_count($users->getContent(), 'class="ft-topbar-search"'))->toBe(0);

    $this->actingAs($admin)->get('/admin/center-users')->assertForbidden();

    foreach ([
        '/admin/users',
        '/admin/schemes',
        '/admin/centers',
        '/admin/attendances',
        '/admin/employee-routes',
        '/admin/admissions',
        '/admin/leave-requests',
        '/admin/reports',
        '/admin/app-update-settings',
        '/admin/device-management',
    ] as $uri) {
        $this->actingAs($admin)->get($uri)->assertOk();
    }
});

it('lets a director view assigned-scope pages but not admin-only management', function () {
    $director = User::query()->where('login_id', 'fielddirector')->firstOrFail();
    expect($director->role)->toBe(UserRole::Director->value);

    $this->actingAs($director)->get('/admin')->assertOk();
    $this->actingAs($director)->get('/admin/schemes')->assertOk();
    $this->actingAs($director)->get('/admin/users')->assertOk();
    $this->actingAs($director)->get('/admin/centers')->assertOk();
    $this->actingAs($director)->get('/admin/admissions')->assertOk();
    $this->actingAs($director)->get('/admin/leave-requests')->assertOk();
    $this->actingAs($director)->get('/admin/attendances')->assertOk();
    $this->actingAs($director)->get('/admin/reports')->assertOk();
    $this->actingAs($director)->get('/admin/directors')->assertForbidden();
    $this->actingAs($director)->get('/admin/center-users')->assertForbidden();
    $this->actingAs($director)->get('/admin/employees')->assertNotFound();
    $this->actingAs($director)->get('/admin/app-update-settings')->assertForbidden();
    $this->actingAs($director)->get('/admin/device-management')->assertForbidden();
    $this->actingAs($director)->get('/admin/projects')->assertForbidden();
    $this->actingAs($director)->get('/admin/schemes/create')->assertForbidden();
});

it('blocks a project manager from the web application', function () {
    $projectHead = User::query()->where('login_id', 'projecthead')->firstOrFail();

    expect($projectHead->role)->toBe(UserRole::ProjectHead->value)
        ->and($projectHead->is_active)->toBeFalse();

    $this->actingAs($projectHead)->get('/admin')->assertForbidden();
    $this->actingAs($projectHead)->get('/admin/centers')->assertForbidden();
    $this->actingAs($projectHead)->get('/admin/schemes')->assertForbidden();
    $this->actingAs($projectHead)->get('/admin/users')->assertForbidden();
    $this->actingAs($projectHead)->get('/admin/project-heads')->assertForbidden();
    $this->actingAs($projectHead)->get('/admin/directors')->assertForbidden();

    $this->postJson('/api/login', [
        'login_id' => 'projecthead',
        'password' => 'ProjectHead@123',
        'device_id' => 'device-retired-pm',
    ])->assertStatus(422);
});

it('lets an admin open a full employee route map', function () {
    $admin = User::query()->where('login_id', 'admin')->firstOrFail();
    $employee = Employee::query()->where('mobile', '9876543210')->firstOrFail();
    $attendance = Attendance::create([
        'employee_id' => $employee->id,
        'attendance_date' => AttendanceCalendar::today()->toDateString(),
        'punch_in_time' => '09:00:00',
        'punch_in_latitude' => 18.5204,
        'punch_in_longitude' => 73.8567,
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);

    $this->actingAs($admin)
        ->withSession([
            AdminCenterContext::SESSION_KEY => $employee->center_id,
        ])
        ->get('/admin/employee-routes/'.$attendance->id)
        ->assertOk()
        ->assertSee('Employee Route', false);
});

it('blocks a center manager from project administration', function () {
    $centerManager = User::query()->where('login_id', 'centermgr')->firstOrFail();

    $this->actingAs($centerManager)->get('/admin')->assertOk();
    $this->actingAs($centerManager)->get('/admin/center-users')->assertOk();
    $this->actingAs($centerManager)->get('/admin/users')->assertForbidden();
    $this->actingAs($centerManager)->get('/admin/centers')->assertOk();
    $this->actingAs($centerManager)->get('/admin/schemes')->assertForbidden();
    $this->actingAs($centerManager)->get('/admin/centers/create')->assertForbidden();
    $this->actingAs($centerManager)->get('/admin/directors')->assertForbidden();

    Livewire::actingAs($centerManager)
        ->test(ListCenters::class)
        ->assertActionHidden('create');

    Livewire::actingAs($centerManager)
        ->test(CreateCenter::class)
        ->assertForbidden();

    $response = $this->actingAs($centerManager, 'sanctum')
        ->postJson('/api/manager/centers', [
            'name' => 'Unauthorized Center',
        ]);

    expect($response->status())->toBeIn([404, 405]);
});

it('lets an admin create centers', function () {
    $admin = User::query()->where('login_id', 'admin')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(ListCenters::class)
        ->assertActionVisible('create');

    Livewire::actingAs($admin)
        ->test(CreateCenter::class)
        ->assertSuccessful();
});

it('shows a profile menu with logout for every web admin role', function (string $loginId) {
    $user = User::query()->where('login_id', $loginId)->firstOrFail();

    if ($loginId === 'admin') {
        $this->withSession([
            AdminCenterContext::SESSION_KEY => Center::query()->where('code', 'C1')->value('id'),
        ]);
    }

    $response = $this->actingAs($user)
        ->get('/admin')
        ->assertOk()
        ->assertSee('ft-topbar-profile-trigger', false)
        ->assertSee('>Profile</a>', false)
        ->assertSee('>Change Password</a>', false)
        ->assertSee('>Logout</button>', false)
        ->assertSee('/admin/logout', false)
        ->assertSee('/admin/profile', false)
        ->assertSee('/admin/change-password', false);

    if ($loginId === 'admin') {
        $response
            ->assertSee('Main Dashboard', false)
            ->assertSee('Center Dashboard', false)
            ->assertSee('Main Admin Dashboard', false);
    } else {
        $response
            ->assertDontSee('Main Dashboard', false)
            ->assertSee('Center Dashboard', false)
            ->assertDontSee('Change Center', false);
    }

    if (in_array($loginId, ['admin', 'fielddirector'], true)) {
        $response
            ->assertSee('>Account Settings</a>', false)
            ->assertSee('/admin/account-settings', false);
    } else {
        $response
            ->assertDontSee('>Account Settings</a>', false)
            ->assertDontSee('/admin/account-settings', false);
    }
})->with([
    'admin' => 'admin',
    'director' => 'fielddirector',
    'center manager' => 'centermgr',
]);

it('logs every web admin role out through filament and blocks returning to admin pages', function (string $loginId) {
    $user = User::query()->where('login_id', $loginId)->firstOrFail();

    if ($loginId === 'admin') {
        $this->withSession([
            AdminCenterContext::SESSION_KEY => Center::query()->where('code', 'C1')->value('id'),
        ]);
    }

    $dashboard = $this->actingAs($user)->get('/admin');
    $dashboard->assertOk();
    expect((string) $dashboard->headers->get('Cache-Control'))->toContain('no-store');

    $this->actingAs($user)
        ->post('/admin/logout')
        ->assertRedirect('/admin/login');

    $this->app['auth']->forgetGuards();

    $this->assertGuest();

    $this->get('/admin')->assertRedirect('/admin/login');
    $this->get('/admin/profile')->assertRedirect('/admin/login');
    $this->get('/admin/change-password')->assertRedirect('/admin/login');
    $this->get('/admin/account-settings')->assertRedirect('/admin/login');
})->with([
    'admin' => 'admin',
    'director' => 'fielddirector',
    'center manager' => 'centermgr',
]);

it('lets a web admin open profile and change password pages', function () {
    $admin = User::query()->where('login_id', 'admin')->firstOrFail();

    $this->actingAs($admin)->get('/admin/profile')->assertOk()->assertSee('Account Settings');
    $this->actingAs($admin)->get('/admin/change-password')->assertOk()->assertSee('Change Password');
    $this->actingAs($admin)->get('/admin/account-settings')->assertOk()->assertSee('Account Settings');
});

it('keeps punch photos and locations on the attendance view instead of the list', function () {
    $admin = User::query()->where('login_id', 'admin')->firstOrFail();
    $employee = Employee::query()->where('mobile', '9876543210')->firstOrFail();
    Storage::fake('public');
    Storage::disk('public')->put('attendance/in.jpg', 'in-photo');
    Storage::disk('public')->put('attendance/out.jpg', 'out-photo');
    $attendance = Attendance::create([
        'employee_id' => $employee->id,
        'attendance_date' => AttendanceCalendar::today()->toDateString(),
        'punch_in_time' => '09:00:00',
        'punch_out_time' => '18:00:00',
        'punch_in_location' => 'Pune HQ',
        'punch_out_location' => 'Pune Field',
        'punch_in_latitude' => 18.5204,
        'punch_in_longitude' => 73.8567,
        'punch_out_latitude' => 18.5310,
        'punch_out_longitude' => 73.8440,
        'punch_in_photo' => 'attendance/in.jpg',
        'punch_out_photo' => 'attendance/out.jpg',
        'attendance_status' => 'Present',
        'approval_status' => 'Pending',
    ]);

    $inUrl = PublicStorage::url('attendance/in.jpg');
    $outUrl = PublicStorage::url('attendance/out.jpg');

    $this->withSession([
        AdminCenterContext::SESSION_KEY => $employee->center_id,
    ]);

    $this->actingAs($admin)
        ->get('/admin/attendances')
        ->assertOk()
        ->assertSee('Employee', false)
        ->assertSee('Scheme / Project', false)
        ->assertSee('Center', false)
        ->assertSee('Attendance Date', false)
        ->assertSee('Punch In Time', false)
        ->assertSee('Punch Out Time', false)
        ->assertDontSee('Punch In Photo', false)
        ->assertDontSee('Punch Out Photo', false)
        ->assertDontSee('Punch in location', false)
        ->assertDontSee('Punch out location', false)
        ->assertDontSee('Open in Google Maps', false);

    $this->actingAs($admin)
        ->get('/admin/attendances/'.$attendance->id)
        ->assertOk()
        ->assertSee('Punch In Photo', false)
        ->assertSee('Punch Out Photo', false)
        ->assertSee('Pune HQ', false)
        ->assertSee('Pune Field', false)
        ->assertSee('Open in Google Maps', false)
        ->assertSee('https://www.google.com/maps?q=', false)
        ->assertSee($inUrl, false)
        ->assertSee($outUrl, false)
        ->assertSee('target="_blank"', false);

    $this->get('/storage/attendance/in.jpg')->assertOk();
    $this->get('/storage/attendance/out.jpg')->assertOk();
});
