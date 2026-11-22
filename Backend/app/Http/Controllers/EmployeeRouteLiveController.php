<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use App\Services\LiveRouteTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeRouteLiveController extends Controller
{
    public function __construct(
        private readonly LiveRouteTrackingService $tracking,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return response()->json($this->tracking->overview($user));
    }

    public function show(Request $request, Attendance $attendance): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $afterId = $request->filled('after_id') ? $request->integer('after_id') : null;
        $afterRecordedAt = $request->filled('after_recorded_at')
            ? $request->string('after_recorded_at')->toString()
            : null;

        return response()->json($this->tracking->feed($user, $attendance, $afterId, $afterRecordedAt));
    }
}
