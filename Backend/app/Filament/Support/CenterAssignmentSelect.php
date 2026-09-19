<?php

namespace App\Filament\Support;

use App\Models\Center;
use App\Models\Scheme;
use App\Models\User;
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
                    ? 'Assign this Center Manager to one or more Centers. The Center list shows these assignments automatically.'
                    : 'Project Head can access only the selected Centers, even when they belong to the same Scheme.',
            )
            ->saveRelationshipsUsing(function (User $record, $state) use ($access, $user, $name): void {
                $selected = array_map('intval', $state ?? []);
                $visible = $user ? $access->visibleCenterIds($user) : [];
                $relation = $record->{$name}();

                if ($visible === null) {
                    $relation->sync($selected);

                    return;
                }

                $selected = array_values(array_intersect($selected, $visible));
                $keep = $relation
                    ->whereNotIn('centers.id', $visible)
                    ->pluck('centers.id')
                    ->all();

                $relation->sync(array_values(array_unique(array_merge($keep, $selected))));
            });
    }
}
