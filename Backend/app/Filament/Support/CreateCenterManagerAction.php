<?php

namespace App\Filament\Support;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\CenterManagerAccountService;
use App\Services\OrganizationAccessService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;

final class CreateCenterManagerAction
{
    public static function make(): Action
    {
        return Action::make('createCenterManager')
            ->label('Create Center Manager')
            ->icon('heroicon-o-user-plus')
            ->modalHeading('Create Center Manager')
            ->modalSubmitActionLabel('Save')
            ->visible(fn (): bool => self::allowed())
            ->authorize(fn (): bool => self::allowed())
            ->schema([
                Select::make('existing_manager_id')
                    ->label('Existing Center Manager')
                    ->placeholder('Create a new Center Manager')
                    ->options(fn (): array => self::managerOptions())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->nullable()
                    ->helperText('Leave this blank to create a new Center Manager. Choosing an existing manager assigns them only to the centers selected below.'),
                TextInput::make('name')
                    ->label('Name')
                    ->required(fn (Get $get): bool => blank($get('existing_manager_id')))
                    ->visible(fn (Get $get): bool => blank($get('existing_manager_id')))
                    ->maxLength(255),
                TextInput::make('mobile')
                    ->label('Mobile Number')
                    ->required(fn (Get $get): bool => blank($get('existing_manager_id')))
                    ->visible(fn (Get $get): bool => blank($get('existing_manager_id')))
                    ->length(10)
                    ->regex('/^[6-9][0-9]{9}$/')
                    ->helperText('Login ID will be this number. Default password is the last 4 digits. An existing Center Manager with this name and number is reused.'),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->nullable()
                    ->visible(fn (Get $get): bool => blank($get('existing_manager_id')))
                    ->maxLength(255),
                Select::make('center_ids')
                    ->label('Centers')
                    ->options(fn (): array => self::centerOptions())
                    ->multiple()
                    ->required()
                    ->preload()
                    ->searchable()
                    ->default(fn (): array => self::defaultCenters())
                    ->helperText('Choose one or more of your centers. A center that already has a Center Manager switches to this manager. Their other centers stay as they are.'),
            ])
            ->action(function (array $data): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User && self::allowed(), 403);

                $result = app(CenterManagerAccountService::class)->provisionForCoordinator($actor, $data);

                if ($result['created'] && is_string($result['password'])) {
                    CenterManagerAccountService::credentialNotification(
                        $result['login_id'],
                        $result['password'],
                    )->send();

                    return;
                }

                Notification::make()
                    ->title('Existing Center Manager assigned.')
                    ->success()
                    ->send();
            });
    }

    private static function allowed(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User
            && $actor->isCenterCoordinator()
            && app(OrganizationAccessService::class)->canManageCenterManagers($actor);
    }

    /**
     * @return array<int, string>
     */
    private static function managerOptions(): array
    {
        return User::query()
            ->where('role', UserRole::CenterManager->value)
            ->where('is_center_login', false)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $user): array => [
                $user->id => trim($user->name.' ('.$user->login_id.')'),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function centerOptions(): array
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            return [];
        }

        return app(OrganizationAccessService::class)
            ->centerQuery($actor)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return list<int>
     */
    private static function defaultCenters(): array
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            return [];
        }

        $ids = app(OrganizationAccessService::class)->visibleCenterIds($actor) ?? [];

        return count($ids) === 1 ? $ids : [];
    }
}
