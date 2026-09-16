<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DirectorSeeder::class);
        $this->call(MaharashtraGeoSeeder::class);

        if (! app()->environment('production')) {
            $this->call(DemoOrganizationSeeder::class);
        }
    }
}
