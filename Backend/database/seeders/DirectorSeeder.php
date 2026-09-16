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
        $admin = User::query()->firstOrCreate(
            ['login_id' => 'director'],
            [
                'name' => 'Admin',
                'email' => 'director@fieldtrack.local',
                'password' => Hash::make('Director@123'),
                'role' => UserRole::Admin->value,
                'is_active' => true,
                'must_change_password' => app()->environment('production'),
            ],
        );

        if ($admin->role !== UserRole::Admin->value) {
            $admin->role = UserRole::Admin->value;
            if ($admin->name === 'Director') {
                $admin->name = 'Admin';
            }
            $admin->save();
        }
    }
}
