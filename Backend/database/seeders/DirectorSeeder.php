<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DirectorSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['login_id' => 'director'],
            [
                'name' => 'Director',
                'email' => 'director@fieldtrack.local',
                'password' => Hash::make('Director@123'),
                'role' => UserRole::Director->value,
                'is_active' => true,
                'must_change_password' => app()->environment('production'),
            ],
        );
    }
}
