<?php

use App\Enums\UserRole;
use App\Models\Center;
use App\Models\Employee;
use App\Models\Scheme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function seedOrg(): array
{
    $projectA = Scheme::create(['name' => 'Project A', 'code' => 'PA', 'is_active' => true]);
    $projectB = Scheme::create(['name' => 'Project B', 'code' => 'PB', 'is_active' => true]);
    $centerA = Center::create(['scheme_id' => $projectA->id, 'name' => 'Center A', 'code' => 'CA', 'is_active' => true]);
    $centerB = Center::create(['scheme_id' => $projectB->id, 'name' => 'Center B', 'code' => 'CB', 'is_active' => true]);

    $admin = User::create([
        'name' => 'Admin',
        'email' => 'dir@test.local',
        'login_id' => 'director',
        'password' => Hash::make('Director@123'),
        'role' => UserRole::Admin->value,
        'is_active' => true,
    ]);

    $director = User::create([
        'name' => 'Director',
        'email' => 'fielddirector@test.local',
        'login_id' => 'fielddirector',
        'password' => Hash::make('Director@123'),
        'role' => UserRole::Director->value,
        'is_active' => true,
    ]);

    $projectHead = User::create([
        'name' => 'PH',
        'email' => 'ph@test.local',
        'login_id' => 'projecthead',
        'password' => Hash::make('ProjectHead@123'),
        'role' => UserRole::ProjectHead->value,
        'is_active' => true,
    ]);
    $projectHead->headedCenters()->attach($centerA->id);

    $centerManager = User::create([
        'name' => 'CM',
        'email' => 'cm@test.local',
        'login_id' => 'centermgr',
        'password' => Hash::make('CenterMgr@123'),
        'role' => UserRole::CenterManager->value,
        'is_active' => true,
    ]);
    $centerManager->managedCenters()->attach($centerA->id);

    $empA = Employee::create([
        'center_id' => $centerA->id,
        'full_name' => 'Emp A',
        'mobile' => '9000000001',
        'status' => true,
    ]);
    $userA = User::create([
        'employee_id' => $empA->id,
        'name' => 'Emp A',
        'email' => 'empa@test.local',
        'login_id' => '9000000001',
        'password' => Hash::make('Employee@123'),
        'role' => UserRole::Employee->value,
        'is_active' => true,
    ]);

    $empB = Employee::create([
        'center_id' => $centerB->id,
        'full_name' => 'Emp B',
        'mobile' => '9000000002',
        'status' => true,
    ]);
    User::create([
        'employee_id' => $empB->id,
        'name' => 'Emp B',
        'email' => 'empb@test.local',
        'login_id' => '9000000002',
        'password' => Hash::make('Employee@123'),
        'role' => UserRole::Employee->value,
        'is_active' => true,
    ]);

    return compact('admin', 'director', 'projectHead', 'centerManager', 'empA', 'empB', 'userA', 'centerA', 'centerB', 'projectA', 'projectB');
}
