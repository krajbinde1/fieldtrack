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
        $admin = User::query()
            ->where('role', UserRole::Admin->value)
            ->orderBy('id')
            ->first()
            ?? User::query()->where('login_id', 'admin')->first()
            ?? User::query()->where('login_id', 'director')->first();

        if ($admin === null) {
            User::query()->create([
                'name' => 'Admin',
                'email' => 'director@fieldtrack.local',
                'login_id' => 'admin',
                'password' => Hash::make('Director@123'),
                'role' => UserRole::Admin->value,
                'is_active' => true,
                'must_change_password' => app()->environment('production'),
            ]);

            return;
        }

        if ($admin->login_id !== 'admin') {
            $admin->login_id = 'admin';
        }

        if ($admin->role !== UserRole::Admin->value) {
            $admin->role = UserRole::Admin->value;
            if ($admin->name === 'Director') {
                $admin->name = 'Admin';
            }
        }

        if ($admin->isDirty()) {
            $admin->save();
        }
    }
}
