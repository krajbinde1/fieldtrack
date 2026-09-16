<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdmissionTarget;
use App\Models\Employee;
use App\Services\AdmissionTargetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EmployeeAdmissionTargetController extends Controller
{
    public function __construct(private readonly AdmissionTargetService $targets) {}

    public function summary(Request $request): JsonResponse
    {
        $metrics = $this->targets->summaryForEmployee(
            $this->employee($request),
            $this->preset($request),
        );

        return $this->ok('Admission target performance loaded.', $metrics);
    }

    public function index(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $items = $this->targets->listAssigned($request->user(), [
            'employee_id' => $employee->id,
        ])->map(fn (AdmissionTarget $target) => $this->decorate($target))->values();

        return $this->ok('Admission targets loaded.', $items);
    }

    private function employee(Request $request): Employee
    {
        $user = $request->user();
        abort_unless($user?->employee_id, 403, 'Employee profile is not linked to this account.');

        return Employee::query()
            ->with(['center.scheme'])
            ->findOrFail((int) $user->employee_id);
    }

    private function preset(Request $request): string
    {
        $preset = (string) $request->query('preset', 'this_week');
        $allowed = ['this_week', 'week', 'last_week', 'this_month', 'month', 'last_month'];

        return in_array($preset, $allowed, true) ? $preset : 'this_week';
    }

    /**
     * @return array<string, mixed>
     */
    private function decorate(AdmissionTarget $target): array
    {
        $payload = $target->toApiArray();
        $payload = array_merge($payload, $this->periodMetrics(
            (int) $target->employee_id,
            $target->period_start,
            $target->period_end,
            (int) $target->target_count,
        ));

        $payload['weekly_splits'] = collect($payload['weekly_splits'] ?? [])
            ->map(function (array $week) use ($target): array {
                return array_merge($week, $this->periodMetrics(
                    (int) $target->employee_id,
                    Carbon::parse((string) $week['period_start']),
                    Carbon::parse((string) $week['period_end']),
                    (int) ($week['target_count'] ?? 0),
                ));
            })
            ->values()
            ->all();

        return $payload;
    }

    /**
     * @return array{achieved: int, remaining: int, percentage: float}
     */
    private function periodMetrics(int $employeeId, Carbon $start, Carbon $end, int $target): array
    {
        $achieved = $this->targets->achievedCount($employeeId, $start, $end);
        $remaining = max(0, $target - $achieved);
        $percentage = $target > 0
            ? round(($achieved / $target) * 100, 1)
            : ($achieved > 0 ? 100.0 : 0.0);

        return [
            'achieved' => $achieved,
            'remaining' => $remaining,
            'percentage' => $percentage,
        ];
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
