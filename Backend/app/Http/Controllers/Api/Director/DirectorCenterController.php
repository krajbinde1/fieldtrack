<?php

namespace App\Http\Controllers\Api\Director;

use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Services\CenterMonitoringService;
use App\Services\OrganizationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DirectorCenterController extends Controller
{
    public function __construct(
        private readonly OrganizationAccessService $access,
        private readonly CenterMonitoringService $monitoring,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $centers = $this->access->centerQuery($user)
            ->with(['scheme:id,name', 'centerManagers:id,name'])
            ->where('is_active', true)
            ->when(filled($validated['search'] ?? null), function ($query) use ($validated): void {
                $term = '%'.$validated['search'].'%';
                $query->where('name', 'like', $term);
            })
            ->orderBy('name')
            ->get();

        $payload = $this->monitoring->payloads($user, $centers);

        return response()->json([
            'success' => true,
            'data' => $payload,
            'meta' => [
                'total' => count($payload),
            ],
        ]);
    }

    public function show(Request $request, Center $center): JsonResponse
    {
        $user = $request->user();
        $this->access->assertCanViewCenter($user, (int) $center->id);
        $center->loadMissing(['scheme:id,name', 'centerManagers:id,name']);

        $payloads = $this->monitoring->payloads($user, collect([$center]));

        return response()->json([
            'success' => true,
            'data' => $payloads[0] ?? $this->monitoring->emptyPayload($center),
        ]);
    }
}
