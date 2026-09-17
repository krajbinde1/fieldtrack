<?php

namespace App\Http\Controllers\Api;

use App\Enums\FieldActivityType;
use App\Http\Controllers\Controller;
use App\Models\FieldActivity;
use App\Services\FieldActivityService;
use App\Services\OrganizationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeFieldActivityController extends Controller
{
    public function __construct(
        private readonly FieldActivityService $activities,
        private readonly OrganizationAccessService $access,
    ) {}

    public function types(): JsonResponse
    {
        return $this->ok('Field activity types loaded.', FieldActivityType::options());
    }

    public function index(Request $request): JsonResponse
    {
        $items = $this->access->fieldActivityQuery($request->user())
            ->with($this->activities->relations())
            ->latest('activity_at')
            ->limit(200)
            ->get()
            ->map(fn (FieldActivity $activity) => $activity->toApiArray())
            ->values();

        return $this->ok('Field activities loaded.', $items);
    }

    public function show(Request $request, FieldActivity $fieldActivity): JsonResponse
    {
        $this->access->assertCanViewFieldActivity($request->user(), $fieldActivity);

        return $this->ok(
            'Field activity loaded.',
            $fieldActivity->loadMissing($this->activities->relations())->toApiArray(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'activity_type' => ['required', Rule::enum(FieldActivityType::class)],
            'activity_name' => ['nullable', 'string', 'max:255'],
            'activity_at' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location' => ['required', 'string', 'max:500'],
        ]);

        $activity = $this->activities->createForEmployee(
            $request->user(),
            $payload,
            $request->file('photo'),
        );

        return $this->ok('Activity submitted.', $activity->toApiArray(), 201);
    }

    private function ok(string $message, mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => $status < 400,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}
