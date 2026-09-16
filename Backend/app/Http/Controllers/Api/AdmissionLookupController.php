<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaharashtraDistrict;
use App\Models\MaharashtraTaluka;
use App\Models\Scheme;
use App\Support\AdmissionLookups;
use Database\Seeders\MaharashtraGeoSeeder;
use Illuminate\Http\JsonResponse;

class AdmissionLookupController extends Controller
{
    public function schemes(): JsonResponse
    {
        $schemes = Scheme::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return $this->ok('Active schemes loaded.', $schemes);
    }

    public function districts(): JsonResponse
    {
        MaharashtraGeoSeeder::ensure();

        $districts = MaharashtraDistrict::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return $this->ok('Districts loaded.', [
            'state' => AdmissionLookups::STATE_MAHARASHTRA,
            'districts' => $districts,
            'genders' => AdmissionLookups::genders(),
            'religions' => AdmissionLookups::religions(),
            'castes' => AdmissionLookups::castes(),
        ]);
    }

    public function talukas(MaharashtraDistrict $district): JsonResponse
    {
        MaharashtraGeoSeeder::ensure();

        $talukas = MaharashtraTaluka::query()
            ->where('district_id', $district->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'district_id']);

        return $this->ok('Talukas loaded.', [
            'district' => [
                'id' => $district->id,
                'name' => $district->name,
            ],
            'talukas' => $talukas,
        ]);
    }

    private function ok(string $message, mixed $data = null): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ]);
    }
}
