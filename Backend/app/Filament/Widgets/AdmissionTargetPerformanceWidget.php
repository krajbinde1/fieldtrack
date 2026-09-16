<?php

namespace App\Filament\Widgets;

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

        return (bool) ($user?->isAdmin() || $user?->isDirector() || $user?->isProjectHead() || $user?->isCenterManager());
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

        $metrics = app(AdmissionTargetService::class)->teamPerformance($user, 'this_week');

        return [
            Stat::make('Target', (string) $metrics['target']),
            Stat::make('Achieved', (string) $metrics['achieved']),
            Stat::make('Remaining', (string) $metrics['remaining']),
            Stat::make('Achievement %', $metrics['percentage'].'%'),
        ];
    }
}
