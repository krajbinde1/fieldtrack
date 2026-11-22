<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\AdminCenterContext;
use App\Services\AdmissionTargetService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdmissionTargetPerformanceWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Admission Target Performance';

    protected ?string $description = 'This week · confirmed admissions only';

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isCenterCoordinator() || $user?->isCenterManager());
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        if ($user === null) {
            return [
                Stat::make('Target', '0'),
                Stat::make('Achieved', '0'),
                Stat::make('Remaining', '0'),
                Stat::make('Achievement %', '0%'),
            ];
        }

        $centerId = $user instanceof User
            ? app(AdminCenterContext::class)->idFor($user)
            : null;
        $filters = $centerId === null ? [] : ['center_id' => $centerId];
        $metrics = app(AdmissionTargetService::class)->teamPerformance($user, 'this_week', null, null, $filters);

        return [
            Stat::make('Target', (string) $metrics['target']),
            Stat::make('Achieved', (string) $metrics['achieved']),
            Stat::make('Remaining', (string) $metrics['remaining']),
            Stat::make('Achievement %', $metrics['percentage'].'%'),
        ];
    }
}
