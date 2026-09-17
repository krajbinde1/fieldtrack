<?php

use App\Enums\UserRole;
use App\Filament\Auth\Login;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
});

it('lets the existing top-level login sign in as admin', function () {
    $user = User::query()->where('login_id', 'director')->firstOrFail();
    expect($user->role)->toBe(UserRole::Admin->value);

    Livewire::test(Login::class)
        ->fillForm([
            'login_id' => 'director',
            'password' => 'Director@123',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $this->assertAuthenticatedAs($user);
});

it('lets an admin open organization, people, and field-operation pages', function () {
    $admin = User::query()->where('login_id', 'director')->firstOrFail();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Param FieldTrack');

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

it('blocks a project head from project-head administration', function () {
    $projectHead = User::query()->where('login_id', 'projecthead')->firstOrFail();

    $this->actingAs($projectHead)->get('/admin')->assertOk();
    $this->actingAs($projectHead)->get('/admin/centers')->assertOk();
    $this->actingAs($projectHead)->get('/admin/schemes')->assertOk();
    $this->actingAs($projectHead)->get('/admin/users')->assertOk();
    $this->actingAs($projectHead)->get('/admin/project-heads')->assertForbidden();
    $this->actingAs($projectHead)->get('/admin/directors')->assertForbidden();
});

it('lets an admin open a full employee route map', function () {
    $admin = User::query()->where('login_id', 'director')->firstOrFail();
    $employee = \App\Models\Employee::query()->where('mobile', '9876543210')->firstOrFail();
    $attendance = \App\Models\Attendance::create([
        'employee_id' => $employee->id,
        'attendance_date' => \App\Support\AttendanceCalendar::today()->toDateString(),
        'punch_in_time' => '09:00:00',
        'punch_in_latitude' => 18.5204,
        'punch_in_longitude' => 73.8567,
        'attendance_status' => 'Punched In',
        'approval_status' => 'Pending',
    ]);

    $this->actingAs($admin)
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
});

it('shows a profile menu with logout for every web admin role', function (string $loginId) {
    $user = User::query()->where('login_id', $loginId)->firstOrFail();

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk()
        ->assertSee('ft-topbar-profile-trigger', false)
        ->assertSee('>Profile</a>', false)
        ->assertSee('>Change Password</a>', false)
        ->assertSee('>Logout</button>', false)
        ->assertSee('/admin/logout', false)
        ->assertSee('/admin/profile', false)
        ->assertSee('/admin/change-password', false);
})->with([
    'admin' => 'director',
    'director' => 'fielddirector',
    'project head' => 'projecthead',
    'center manager' => 'centermgr',
]);

it('logs every web admin role out through filament and blocks returning to admin pages', function (string $loginId) {
    $user = User::query()->where('login_id', $loginId)->firstOrFail();

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
})->with([
    'admin' => 'director',
    'director' => 'fielddirector',
    'project head' => 'projecthead',
    'center manager' => 'centermgr',
]);

it('lets a web admin open profile and change password pages', function () {
    $admin = User::query()->where('login_id', 'director')->firstOrFail();

    $this->actingAs($admin)->get('/admin/profile')->assertOk();
    $this->actingAs($admin)->get('/admin/change-password')->assertOk()->assertSee('Change Password');
});

it('keeps punch photos and locations on the attendance view instead of the list', function () {
    $admin = User::query()->where('login_id', 'director')->firstOrFail();
    $employee = \App\Models\Employee::query()->where('mobile', '9876543210')->firstOrFail();
    $attendance = \App\Models\Attendance::create([
        'employee_id' => $employee->id,
        'attendance_date' => \App\Support\AttendanceCalendar::today()->toDateString(),
        'punch_in_time' => '09:00:00',
        'punch_out_time' => '18:00:00',
        'punch_in_location' => 'Pune HQ',
        'punch_out_location' => 'Pune Field',
        'punch_in_latitude' => 18.5204,
        'punch_in_longitude' => 73.8567,
        'punch_out_latitude' => 18.5310,
        'punch_out_longitude' => 73.8440,
        'attendance_status' => 'Present',
        'approval_status' => 'Pending',
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
        ->assertSee('https://www.google.com/maps?q=', false);
});
