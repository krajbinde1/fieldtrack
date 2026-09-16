<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DirectorSeeder::class);

        if (! app()->environment('production')) {
            $this->call(DemoOrganizationSeeder::class);
        }
    }
}
