<?php

use App\Filament\Auth\Login;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
});

it('lets a director sign in to the web admin', function () {
    $user = User::query()->where('login_id', 'director')->firstOrFail();

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

it('lets a director open organization and field-operation pages', function () {
    $director = User::query()->where('login_id', 'director')->firstOrFail();

    $this->actingAs($director)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Param FieldTrack');

    foreach ([
        '/admin/projects',
        '/admin/project-heads',
        '/admin/centers',
        '/admin/center-managers',
        '/admin/employees',
        '/admin/attendances',
        '/admin/employee-routes',
        '/admin/reports',
    ] as $uri) {
        $this->actingAs($director)->get($uri)->assertOk();
    }
});

it('blocks a project head from project-head administration', function () {
    $projectHead = User::query()->where('login_id', 'projecthead')->firstOrFail();

    $this->actingAs($projectHead)->get('/admin')->assertOk();
    $this->actingAs($projectHead)->get('/admin/centers')->assertOk();
    $this->actingAs($projectHead)->get('/admin/project-heads')->assertForbidden();
});

it('lets a director open a full employee route map', function () {
    $director = User::query()->where('login_id', 'director')->firstOrFail();
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

    $this->actingAs($director)
        ->get('/admin/employee-routes/'.$attendance->id)
        ->assertOk()
        ->assertSee('Employee Route', false);
});

it('blocks a center manager from project administration', function () {
    $centerManager = User::query()->where('login_id', 'centermgr')->firstOrFail();

    $this->actingAs($centerManager)->get('/admin')->assertOk();
    $this->actingAs($centerManager)->get('/admin/employees')->assertOk();
    $this->actingAs($centerManager)->get('/admin/centers')->assertOk();
    $this->actingAs($centerManager)->get('/admin/projects')->assertForbidden();
    $this->actingAs($centerManager)->get('/admin/centers/create')->assertForbidden();
});
