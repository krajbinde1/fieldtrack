<?php

namespace App\Filament\Support;

use App\Enums\UserRole;
use App\Models\Center;
use App\Models\User;
use App\Services\OrganizationAccessService;
use App\Support\LoginId;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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
                    $query->where('users.role', UserRole::CenterManager->value);

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
            ->multiple()
            ->preload()
            ->searchable()
            ->optionsLimit(250)
            ->helperText('Assign one or more existing Center Managers. This is the same assignment shown on Users → Assigned Centers.')
            ->createOptionModalHeading('Create New Center Manager')
            ->createOptionForm(self::createForm())
            ->createOptionUsing(fn (array $data): int => self::createManager($data))
            ->createOptionAction(fn (Action $action): Action => $action
                ->label('+ Create New Center Manager')
                ->iconButton(false)
                ->link()
                ->visible(fn (): bool => ($user = auth()->user()) !== null && $access->canManageCenterManagers($user))
            )
            ->saveRelationshipsUsing(function (Center $record, $state): void {
                $selected = array_values(array_unique(array_map('intval', $state ?? [])));
                $ids = $selected === []
                    ? []
                    : User::query()
                        ->where('role', UserRole::CenterManager->value)
                        ->whereIn('id', $selected)
                        ->pluck('id')
                        ->all();

                $record->centerManagers()->sync($ids);
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

    /**
     * @param  array<string, mixed>  $data
     */
    public static function createManager(array $data): int
    {
        $actor = auth()->user();
        abort_unless(
            $actor !== null && app(OrganizationAccessService::class)->canManageCenterManagers($actor),
            403,
        );

        $name = trim((string) ($data['name'] ?? ''));
        $mobile = trim((string) ($data['mobile'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'Name is required.',
            ]);
        }

        if (! preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
            throw ValidationException::withMessages([
                'mobile' => 'Enter a valid 10-digit mobile number.',
            ]);
        }

        LoginId::assertUnique($mobile, attribute: 'mobile');

        if ($email === '') {
            $email = $mobile.'@fieldtrack.local';
        }

        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This email is already taken.',
            ]);
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'login_id' => $mobile,
            'password' => Hash::make(LoginId::defaultPasswordFromMobile($mobile)),
            'role' => UserRole::CenterManager->value,
            'is_active' => true,
            'must_change_password' => true,
        ]);

        return (int) $user->id;
    }
}
