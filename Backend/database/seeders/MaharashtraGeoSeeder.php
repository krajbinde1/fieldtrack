<?php

namespace Database\Seeders;

use App\Models\MaharashtraDistrict;
use App\Models\MaharashtraTaluka;
use App\Support\MaharashtraGeoCatalog;
use Illuminate\Database\Seeder;

class MaharashtraGeoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (MaharashtraGeoCatalog::districts() as $districtIndex => $districtData) {
            $district = MaharashtraDistrict::query()->updateOrCreate(
                ['code' => $districtData['code']],
                [
                    'name' => $districtData['name'],
                    'sort_order' => $districtIndex + 1,
                    'is_active' => true,
                ],
            );

            foreach ($districtData['talukas'] as $talukaIndex => $talukaData) {
                MaharashtraTaluka::query()->updateOrCreate(
                    [
                        'district_id' => $district->id,
                        'code' => $talukaData['code'],
                    ],
                    [
                        'name' => $talukaData['name'],
                        'sort_order' => $talukaData['sort'] ?? ($talukaIndex + 1),
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
