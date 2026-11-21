<?php

namespace App\Filament\Support;

use App\Models\Center;
use App\Models\Scheme;
use App\Models\User;
use App\Services\CenterLoginService;
use App\Services\OrganizationAccessService;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

final class CenterAssignmentSelect
{
    public static function make(string $name = 'headedCenters'): Select
    {
        $access = app(OrganizationAccessService::class);
        $user = auth()->user();

        return Select::make($name)
            ->label('Assigned Center(s)')
            ->relationship(
                name: $name,
                titleAttribute: 'name',
                modifyQueryUsing: function (Builder $query) use ($access, $user): Builder {
                    $query = $user ? $access->centerQuery($user) : $query->whereRaw('1 = 0');

                    return $query
                        ->with('scheme')
                        ->orderBy(
                            Scheme::query()->select('name')->whereColumn('schemes.id', 'centers.scheme_id'),
                        )
                        ->orderBy('centers.name');
                },
            )
            ->getOptionLabelFromRecordUsing(fn (Center $record): string => $record->assignmentLabel())
            ->multiple()
            ->preload()
            ->searchable()
            ->required()
            ->helperText(
                $name === 'managedCenters'
                    ? 'A manager can cover several centers. Each center can have only one active manager.'
                    : ($name === 'coordinatedCenters'
                        ? 'Coordinators can view only the selected centers. A center can have more than one coordinator.'
                        : 'Project Manager can access only the selected Centers, even when they belong to the same Scheme.'),
            )
            ->saveRelationshipsUsing(function (User $record, $state) use ($access, $user, $name): void {
                $selected = array_map('intval', $state ?? []);
                $visible = $user ? $access->visibleCenterIds($user) : [];
                $relation = $record->{$name}();

                if ($visible === null) {
                    $ids = $selected;
                } else {
                    $selected = array_values(array_intersect($selected, $visible));
                    $keep = $relation
                        ->whereNotIn('centers.id', $visible)
                        ->pluck('centers.id')
                        ->all();
                    $ids = array_values(array_unique(array_merge($keep, $selected)));
                }

                if ($name === 'managedCenters') {
                    app(CenterLoginService::class)->assertSoleActiveManager($record, $ids);
                }

                $relation->sync($ids);
            });
    }
}
