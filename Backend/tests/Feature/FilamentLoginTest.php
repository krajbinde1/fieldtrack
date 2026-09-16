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
        '/admin/projects',
        '/admin/centers',
        '/admin/attendances',
        '/admin/employee-routes',
        '/admin/schemes',
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
    $this->actingAs($director)->get('/admin/projects')->assertOk();
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
    $this->actingAs($director)->get('/admin/schemes')->assertForbidden();
    $this->actingAs($director)->get('/admin/projects/create')->assertForbidden();
});

it('blocks a project head from project-head administration', function () {
    $projectHead = User::query()->where('login_id', 'projecthead')->firstOrFail();

    $this->actingAs($projectHead)->get('/admin')->assertOk();
    $this->actingAs($projectHead)->get('/admin/centers')->assertOk();
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
    $this->actingAs($centerManager)->get('/admin/projects')->assertForbidden();
    $this->actingAs($centerManager)->get('/admin/centers/create')->assertForbidden();
    $this->actingAs($centerManager)->get('/admin/directors')->assertForbidden();
});
