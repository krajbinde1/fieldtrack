<?php

namespace App\Filament\Support;

use App\Enums\UserRole;
use App\Models\Center;
use App\Models\User;
use App\Services\OrganizationAccessService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class CenterCoordinatorSelect
{
    public static function make(): Select
    {
        return Select::make('coordinators')
            ->label('Center Coordinator(s)')
            ->relationship(
                name: 'coordinators',
                titleAttribute: 'name',
                modifyQueryUsing: function (Builder $query, Select $component): Builder {
                    $query->where('users.role', UserRole::CenterCoordinator->value)
                        ->where('users.is_center_login', false);

                    $assignedIds = [];
                    $record = $component->getRecord();
                    if ($record instanceof Center && $record->exists) {
                        $assignedIds = $record->coordinators()->pluck('users.id')->all();
                    }

                    $query->where(function (Builder $inner) use ($assignedIds): void {
                        $inner->where('users.is_active', true);
                        if ($assignedIds !== []) {
                            $inner->orWhereIn('users.id', $assignedIds);
                        }
                    });

                    return $query->orderBy('users.name');
                },
            )
            ->getOptionLabelFromRecordUsing(fn (User $record): string => trim($record->name.' ('.$record->login_id.')'))
            ->getOptionLabelsUsing(fn (Select $component): array => PendingCenterAccount::labelsFor($component, PendingCenterAccount::COORDINATORS))
            ->multiple()
            ->preload()
            ->searchable()
            ->optionsLimit(250)
            ->helperText('Assign one or more Center Coordinators. A center can have more than one coordinator.')
            ->visible(fn (string $operation): bool => $operation === 'create' || self::canAssign())
            ->createOptionModalHeading('Create New Coordinator')
            ->createOptionForm(self::createForm())
            ->createOptionUsing(function (array $data, Select $component): int|string {
                abort_unless(self::canCreateCoordinator(), 403);

                return PendingCenterAccount::remember(
                    $component->getLivewire(),
                    PendingCenterAccount::COORDINATORS,
                    $data,
                    UserRole::CenterCoordinator,
                    reuseManager: false,
                );
            })
            ->createOptionAction(fn (Action $action): Action => $action
                ->label('+ Create New Coordinator')
                ->iconButton(false)
                ->link()
                ->visible(fn (): bool => self::canCreateCoordinator())
            )
            ->saveRelationshipsUsing(function (Select $component, Center $record, $state): void {
                $operation = $component->getContainer()?->getOperation();

                if ($operation !== 'create') {
                    abort_unless(self::canAssign(), 403);
                }

                self::sync($component, $record, $state);
            });
    }

    public static function canAssign(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User
            && app(OrganizationAccessService::class)->hasOrganizationWideAccess($actor);
    }

    public static function assignAction(): Action
    {
        return Action::make('assignCoordinators')
            ->label('Assign Coordinators')
            ->modalHeading('Assign Coordinators')
            ->modalSubmitActionLabel('Save')
            ->authorize(fn (): bool => self::canAssign())
            ->schema([
                self::make(),
            ])
            ->mountUsing(function (Action $action, ?Schema $schema): void {
                abort_unless(self::canAssign(), 403);

                $record = $action->getRecord();
                $ids = $record instanceof Center
                    ? $record->coordinators()->pluck('users.id')->map(fn ($id): string => (string) $id)->all()
                    : [];

                $schema?->fill(['coordinators' => $ids]);
            })
            ->before(function (): void {
                abort_unless(self::canAssign(), 403);
            })
            ->action(function (): void {
                Notification::make()
                    ->title('Coordinators updated.')
                    ->success()
                    ->send();
            });
    }

    public static function canCreateCoordinator(): bool
    {
        $actor = auth()->user();

        return $actor !== null
            && in_array(
                UserRole::CenterCoordinator->value,
                app(OrganizationAccessService::class)->creatableOrgUserRoles($actor),
                true,
            );
    }

    /**
     * @return list<TextInput>
     */
    private static function createForm(): array
    {
        return [
            TextInput::make('name')
                ->label('Name')
                ->required()
                ->maxLength(255),
            TextInput::make('mobile')
                ->label('Mobile Number')
                ->required()
                ->length(10)
                ->regex('/^[6-9][0-9]{9}$/')
                ->unique('users', 'login_id')
                ->validationMessages([
                    'unique' => 'This Login ID is already taken.',
                ])
                ->helperText('Login ID will be this number. Default password is the last 4 digits.'),
            TextInput::make('email')
                ->label('Email')
                ->email()
                ->nullable()
                ->unique('users', 'email')
                ->maxLength(255),
        ];
    }

    private static function sync(Select $component, Center $record, mixed $state): void
    {
        $selected = array_values(array_filter(
            is_array($state) ? $state : [],
            fn ($id): bool => filled($id),
        ));

        DB::transaction(function () use ($component, $record, $selected): void {
            $ids = [];

            foreach ($selected as $value) {
                if (PendingCenterAccount::isPending($value)) {
                    abort_unless(self::canCreateCoordinator(), 403);
                    $ids[] = PendingCenterAccount::materialize(
                        $component->getLivewire(),
                        PendingCenterAccount::COORDINATORS,
                        (string) $value,
                        UserRole::CenterCoordinator,
                        reuseManager: false,
                        errorKey: 'coordinators',
                    );

                    continue;
                }

                $ids[] = (int) $value;
            }

            $ids = array_values(array_unique($ids));
            $valid = $ids === []
                ? []
                : User::query()
                    ->where('role', UserRole::CenterCoordinator->value)
                    ->where('is_center_login', false)
                    ->whereIn('id', $ids)
                    ->pluck('id')
                    ->all();

            $record->coordinators()->sync($valid);
        });
    }
}
