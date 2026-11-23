<?php

namespace App\Filament\Support;

use App\Enums\UserRole;
use App\Models\Center;
use App\Models\User;
use App\Services\IssuedManagerCredentials;
use App\Services\OrganizationAccessService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CenterManagerSelect
{
    public static function make(): Select
    {
        $access = app(OrganizationAccessService::class);

        return Select::make('centerManagers')
            ->label('Center Manager')
            ->relationship(
                name: 'centerManagers',
                titleAttribute: 'name',
                modifyQueryUsing: function (Builder $query, Select $component): Builder {
                    $query->where('users.role', UserRole::CenterManager->value)
                        ->where('users.is_center_login', false);

                    $assignedIds = [];
                    $record = $component->getRecord();
                    if ($record instanceof Center && $record->exists) {
                        $assignedIds = $record->centerManagers()->pluck('users.id')->all();
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
            ->getOptionLabelsUsing(fn (Select $component): array => PendingCenterAccount::labelsFor($component, PendingCenterAccount::MANAGERS))
            ->multiple()
            ->preload()
            ->searchable()
            ->optionsLimit(250)
            ->helperText('Assign one active Center Manager. That manager can still be assigned to other centers.')
            ->createOptionModalHeading('Create New Center Manager')
            ->createOptionForm(self::createForm())
            ->createOptionUsing(function (array $data, Select $component): int|string {
                $center = self::centerFrom($component);
                abort_unless(self::canCreate($center), 403);

                return PendingCenterAccount::remember(
                    $component->getLivewire(),
                    PendingCenterAccount::MANAGERS,
                    $data,
                    UserRole::CenterManager,
                    reuseManager: true,
                );
            })
            ->createOptionAction(fn (Action $action): Action => $action
                ->label('+ Create New Center Manager')
                ->iconButton(false)
                ->link()
                ->visible(function (Action $action) use ($access): bool {
                    $actor = auth()->user();
                    if (! $actor instanceof User) {
                        return false;
                    }

                    $record = $action->getRecord();
                    if (! $record instanceof Center) {
                        $livewire = $action->getLivewire();
                        $record = method_exists($livewire, 'getRecord') ? $livewire->getRecord() : null;
                    }

                    if ($record instanceof Center) {
                        return $access->canManageCenterManagers($actor, $record);
                    }

                    return $access->canManageCenterManagers($actor);
                })
            )
            ->saveRelationshipsUsing(function (Select $component, Center $record, $state): void {
                abort_unless(self::canAssign($record), 403);
                self::sync($component, $record, $state);
            });
    }

    public static function canAssign(?Center $center): bool
    {
        $actor = auth()->user();

        return $actor instanceof User
            && $center instanceof Center
            && $center->exists
            && app(OrganizationAccessService::class)->canManageCenterManagers($actor, $center);
    }

    public static function assignAction(): Action
    {
        return Action::make('assignManager')
            ->label('Assign Center Manager')
            ->modalHeading('Assign Center Manager')
            ->modalSubmitActionLabel('Save')
            ->authorize(fn (Center $record): bool => self::canAssign($record))
            ->schema([
                self::make(),
            ])
            ->mountUsing(function (Action $action, ?Schema $schema): void {
                $record = $action->getRecord();
                abort_unless($record instanceof Center && self::canAssign($record), 403);

                $ids = $record->centerManagers()
                    ->where('users.is_center_login', false)
                    ->pluck('users.id')
                    ->map(fn ($id): string => (string) $id)
                    ->all();

                $schema?->fill(['centerManagers' => $ids]);
            })
            ->before(function (Center $record): void {
                abort_unless(self::canAssign($record), 403);
            })
            ->action(function (): void {
                if (app(IssuedManagerCredentials::class)->notify()) {
                    return;
                }

                Notification::make()
                    ->title('Center Manager updated.')
                    ->success()
                    ->send();
            });
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
                ->rule(function (TextInput $component): Closure {
                    return function (string $attribute, mixed $value, Closure $fail) use ($component): void {
                        $mobile = trim((string) $value);
                        $livewire = $component->getLivewire();

                        foreach ([PendingCenterAccount::COORDINATORS, PendingCenterAccount::MANAGERS] as $bucket) {
                            foreach ($livewire->{$bucket} ?? [] as $payload) {
                                if (is_array($payload) && ($payload['mobile'] ?? null) === $mobile) {
                                    $fail('This Login ID is already taken.');

                                    return;
                                }
                            }
                        }

                        $existing = User::query()->where('login_id', $mobile)->first();
                        if ($existing === null) {
                            return;
                        }

                        if ($existing->role === UserRole::CenterManager->value && $existing->is_center_login !== true) {
                            return;
                        }

                        $fail('This Login ID is already taken.');
                    };
                })
                ->validationMessages([
                    'unique' => 'This Login ID is already taken.',
                ])
                ->helperText('Login ID will be this number. Default password is the last 4 digits. An existing Center Manager with this number is reused.'),
            TextInput::make('email')
                ->label('Email')
                ->email()
                ->nullable()
                ->maxLength(255),
        ];
    }

    private static function canCreate(?Center $center): bool
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            return false;
        }

        return app(OrganizationAccessService::class)->canManageCenterManagers($actor, $center);
    }

    private static function centerFrom(Select $component): ?Center
    {
        $record = $component->getRecord();

        return $record instanceof Center ? $record : null;
    }

    private static function sync(Select $component, Center $record, mixed $state): void
    {
        $selected = array_values(array_filter(
            is_array($state) ? $state : [],
            fn ($id): bool => filled($id),
        ));

        DB::transaction(function () use ($component, $record, $selected): void {
            try {
                self::syncSelected($component, $record, $selected);
            } catch (Throwable $exception) {
                app(IssuedManagerCredentials::class)->clear();

                throw $exception;
            }
        });
    }

    /**
     * @param  list<mixed>  $selected
     */
    private static function syncSelected(Select $component, Center $record, array $selected): void
    {
        $ids = [];

        foreach ($selected as $value) {
            if (PendingCenterAccount::isPending($value)) {
                abort_unless(self::canCreate($record), 403);
                $ids[] = PendingCenterAccount::materialize(
                    $component->getLivewire(),
                    PendingCenterAccount::MANAGERS,
                    (string) $value,
                    UserRole::CenterManager,
                    reuseManager: true,
                    errorKey: 'centerManagers',
                );

                continue;
            }

            $ids[] = (int) $value;
        }

        $ids = array_values(array_unique($ids));
        $valid = $ids === []
            ? []
            : User::query()
                ->where('role', UserRole::CenterManager->value)
                ->where('is_center_login', false)
                ->whereIn('id', $ids)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

        if (count($valid) > 1) {
            throw ValidationException::withMessages([
                'centerManagers' => 'A center can have only one active Center Manager.',
            ]);
        }

        $record->centerManagers()->sync($valid);
        $record->forceFill([
            'primary_center_manager_id' => $valid[0] ?? null,
        ])->save();
    }
}
