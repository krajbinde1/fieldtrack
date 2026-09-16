<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Center;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Scheme;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoOrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $project = Project::query()->updateOrCreate(
            ['code' => 'DEMO'],
            [
                'name' => 'Demo Project',
                'description' => 'Local Param FieldTrack sample project',
                'is_active' => true,
            ],
        );

        $projectHead = User::query()->updateOrCreate(
            ['login_id' => 'projecthead'],
            [
                'name' => 'Project Head',
                'email' => 'projecthead@fieldtrack.local',
                'password' => Hash::make('ProjectHead@123'),
                'role' => UserRole::ProjectHead->value,
                'is_active' => true,
                'must_change_password' => false,
            ],
        );
        $projectHead->headedProjects()->syncWithoutDetaching([$project->id]);

        $director = User::query()->updateOrCreate(
            ['login_id' => 'fielddirector'],
            [
                'name' => 'Director',
                'email' => 'fielddirector@fieldtrack.local',
                'password' => Hash::make('Director@123'),
                'role' => UserRole::Director->value,
                'is_active' => true,
                'must_change_password' => false,
            ],
        );
        $director->directedProjects()->syncWithoutDetaching([$project->id]);

        $otherProject = Project::query()->updateOrCreate(
            ['code' => 'OTHER'],
            [
                'name' => 'Other Project',
                'description' => 'Used to verify Project Head isolation',
                'is_active' => true,
            ],
        );

        $center = Center::query()->updateOrCreate(
            ['project_id' => $project->id, 'code' => 'C1'],
            [
                'name' => 'Demo Center',
                'address' => 'Pune',
                'is_active' => true,
            ],
        );

        $otherCenter = Center::query()->updateOrCreate(
            ['project_id' => $otherProject->id, 'code' => 'C9'],
            [
                'name' => 'Other Center',
                'address' => 'Nashik',
                'is_active' => true,
            ],
        );

        $centerManager = User::query()->updateOrCreate(
            ['login_id' => 'centermgr'],
            [
                'name' => 'Center Manager',
                'email' => 'centermgr@fieldtrack.local',
                'password' => Hash::make('CenterMgr@123'),
                'role' => UserRole::CenterManager->value,
                'is_active' => true,
                'must_change_password' => false,
            ],
        );
        $centerManager->managedCenters()->syncWithoutDetaching([$center->id]);

        $otherManager = User::query()->updateOrCreate(
            ['login_id' => 'othermgr'],
            [
                'name' => 'Other Center Manager',
                'email' => 'othermgr@fieldtrack.local',
                'password' => Hash::make('OtherMgr@123'),
                'role' => UserRole::CenterManager->value,
                'is_active' => true,
                'must_change_password' => false,
            ],
        );
        $otherManager->managedCenters()->syncWithoutDetaching([$otherCenter->id]);

        $employee = Employee::query()->updateOrCreate(
            ['mobile' => '9876543210'],
            [
                'center_id' => $center->id,
                'full_name' => 'Field Employee',
                'email' => 'employee@fieldtrack.local',
                'department' => 'Field',
                'designation' => 'Field Executive',
                'joining_date' => now()->toDateString(),
                'base_location' => 'Pune',
                'status' => true,
            ],
        );

        User::query()->updateOrCreate(
            ['login_id' => '9876543210'],
            [
                'employee_id' => $employee->id,
                'name' => $employee->full_name,
                'email' => 'employee@fieldtrack.local',
                'password' => Hash::make('Employee@123'),
                'role' => UserRole::Employee->value,
                'is_active' => true,
                'must_change_password' => false,
            ],
        );

        $otherEmployee = Employee::query()->updateOrCreate(
            ['mobile' => '9876543211'],
            [
                'center_id' => $otherCenter->id,
                'full_name' => 'Other Employee',
                'email' => 'other.employee@fieldtrack.local',
                'department' => 'Field',
                'designation' => 'Field Executive',
                'joining_date' => now()->toDateString(),
                'base_location' => 'Nashik',
                'status' => true,
            ],
        );

        User::query()->updateOrCreate(
            ['login_id' => '9876543211'],
            [
                'employee_id' => $otherEmployee->id,
                'name' => $otherEmployee->full_name,
                'email' => 'other.employee@fieldtrack.local',
                'password' => Hash::make('Employee@123'),
                'role' => UserRole::Employee->value,
                'is_active' => true,
                'must_change_password' => false,
            ],
        );

        Scheme::query()->updateOrCreate(
            ['code' => 'DEMO-SKILL'],
            [
                'name' => 'Demo Skill Development Scheme',
                'description' => 'Sample scheme for local admission testing',
                'is_active' => true,
            ],
        );
    }
}
