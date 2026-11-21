<?php

namespace App\Filament\Resources\Centers;

use App\Filament\Concerns\ScopesRecordsByOrganization;
use App\Filament\Pages\CenterDashboard;
use App\Filament\Resources\Centers\Pages\CreateCenter;
use App\Filament\Resources\Centers\Pages\EditCenter;
use App\Filament\Resources\Centers\Pages\ListCenters;
use App\Filament\Support\CenterCoordinatorSelect;
use App\Filament\Support\CenterManagerSelect;
use App\Models\Center;
use App\Models\Scheme;
use App\Models\User;
use App\Services\AdminCenterContext;
use App\Services\CenterLoginService;
use App\Services\OrganizationAccessService;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CenterResource extends Resource
{
    use ScopesRecordsByOrganization;

    protected static ?string $model = Center::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Organization';

    protected static ?string $navigationLabel = 'Center Dashboard';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isAdmin() || $user?->isDirector() || $user?->isCenterCoordinator() || $user?->isProjectHead() || $user?->isCenterManager());
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return (bool) ($user && app(OrganizationAccessService::class)->canManageCenters($user));
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        return (bool) ($user && app(OrganizationAccessService::class)->canManageCenters($user, $record));
    }

    public static function form(Schema $schema): Schema
    {
        $access = app(OrganizationAccessService::class);
        $user = auth()->user();

        return $schema->components([
            Select::make('scheme_id')
                ->label('Select Scheme')
                ->relationship(
                    name: 'scheme',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn ($query) => $user ? $access->schemeQuery($user) : $query->whereRaw('1=0'),
                )
                ->required()
                ->preload()
                ->searchable()
                ->disabled(fn (): bool => auth()->user()?->isCenterManager() ?? false)
                ->createOptionModalHeading('Create New Scheme')
                ->createOptionForm([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('code')->maxLength(64)->unique(ignoreRecord: true),
                    Textarea::make('description')->columnSpanFull(),
                    Toggle::make('is_active')->label('Active')->default(true),
                ])
                ->createOptionUsing(function (array $data) use ($access): int {
                    $actor = auth()->user();
                    abort_unless($actor && $access->canManageSchemes($actor), 403);

                    $scheme = Scheme::query()->create([
                        'name' => $data['name'],
                        'code' => filled($data['code'] ?? null) ? $data['code'] : null,
                        'description' => filled($data['description'] ?? null) ? $data['description'] : null,
                        'is_active' => (bool) ($data['is_active'] ?? true),
                    ]);

                    return (int) $scheme->getKey();
                })
                ->createOptionAction(fn (Action $action): Action => $action
                    ->label('+ Create New Scheme')
                    ->iconButton(false)
                    ->link()
                    ->visible(fn (): bool => ($actor = auth()->user()) !== null && $access->canManageSchemes($actor))
                )
                ->suffixAction(
                    Action::make('deleteScheme')
                        ->label('Delete Scheme')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->link()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Scheme')
                        ->modalDescription('Delete the selected scheme? This cannot be undone. Deletion is only allowed when the scheme is not used by any records.')
                        ->modalSubmitActionLabel('Delete Scheme')
                        ->visible(fn (): bool => static::canDeleteSchemes())
                        ->authorize(fn (): bool => static::canDeleteSchemes())
                        ->action(function (Get $get, Set $set): void {
                            $schemeId = $get('scheme_id');

                            if (blank($schemeId)) {
                                Notification::make()
                                    ->title('Select a scheme to delete.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            if (! static::deleteUnusedScheme($schemeId)) {
                                Notification::make()
                                    ->title(Scheme::DELETE_BLOCKED_MESSAGE)
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $set('scheme_id', null);

                            Notification::make()
                                ->title(Scheme::DELETED_MESSAGE)
                                ->success()
                                ->send();
                        }),
                ),
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('login_id')
                ->label('Center Login ID')
                ->disabled()
                ->dehydrated(false)
                ->hiddenOn('create')
                ->helperText('Sign-in ID for this center.'),
            TextInput::make('address')->maxLength(255),
            CenterCoordinatorSelect::make(),
            CenterManagerSelect::make()->live()->visibleOn('edit'),
            Select::make('primary_center_manager_id')
                ->label('Primary Center Manager')
                ->options(function (Get $get): array {
                    $selected = $get('centerManagers');
                    $ids = app(CenterLoginService::class)->eligibleManagerIds(is_array($selected) ? $selected : []);
                    if ($ids === []) {
                        return [];
                    }

                    return User::query()
                        ->whereIn('id', $ids)
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (User $user): array => [
                            $user->id => trim($user->name.' ('.$user->login_id.')'),
                        ])
                        ->all();
                })
                ->hiddenOn('create')
                ->visible(function (Get $get): bool {
                    $selected = $get('centerManagers');

                    return count(app(CenterLoginService::class)->eligibleManagerIds(is_array($selected) ? $selected : [])) > 1;
                })
                ->required(false)
                ->searchable()
                ->preload()
                ->helperText('Required when more than one active Center Manager is assigned. Inactive managers cannot be primary.'),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['coordinators', 'centerManagers']))
            ->columns([
                TextColumn::make('index')
                    ->label('Sr. No.')
                    ->rowIndex(),
                TextColumn::make('name')
                    ->label('Center Name')
                    ->searchable(),
                TextColumn::make('scheme.name')
                    ->label('Scheme')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('coordinators.name')
                    ->label('Center Coordinator(s)')
                    ->badge()
                    ->placeholder('Not Assigned')
                    ->action(CenterCoordinatorSelect::assignAction())
                    ->disabledClick(fn (): bool => ! CenterCoordinatorSelect::canAssign()),
                TextColumn::make('centerManagers.name')
                    ->label('Center Manager')
                    ->state(function (Center $record): ?string {
                        $managers = $record->centerManagers
                            ->reject(fn (User $user): bool => $user->isCenterLogin());

                        return ($managers->firstWhere('is_active', true) ?? $managers->sortBy('name')->first())?->name;
                    })
                    ->placeholder('Not Assigned'),
                TextColumn::make('employees_count')
                    ->counts('employees')
                    ->label('Employees'),
                IconColumn::make('is_active')
                    ->label('Is Active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('scheme_id')->label('Scheme / Project')->relationship('scheme', 'name')->preload(),
            ])
            ->recordActionsColumnLabel('Actions')
            ->recordActions([
                Action::make('openDashboard')
                    ->label('Open Dashboard')
                    ->visible(function (): bool {
                        $user = auth()->user();

                        return $user instanceof User
                            && ($user->isAdmin() || $user->isCenterCoordinator() || $user->isCenterManager());
                    })
                    ->action(function (Center $record) {
                        $user = auth()->user();
                        abort_unless(
                            $user instanceof User
                            && ($user->isAdmin() || $user->isCenterCoordinator() || $user->isCenterManager()),
                            403,
                        );

                        app(AdminCenterContext::class)->select($user, (int) $record->id);

                        return redirect()->to(CenterDashboard::getUrl());
                    }),
                EditAction::make(),
            ]);
    }

    public static function resetPasswordAction(): Action
    {
        return Action::make('resetCenterPassword')
            ->label('Reset Password')
            ->icon('heroicon-o-key')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Reset Center Password')
            ->modalDescription('Reset the center login password? The new password is not shown.')
            ->modalSubmitActionLabel('Reset Password')
            ->visible(fn (): bool => auth()->user()?->isAdmin() === true)
            ->authorize(fn (): bool => auth()->user()?->isAdmin() === true)
            ->action(function (Center $record): void {
                abort_unless(auth()->user()?->isAdmin() === true, 403);

                app(CenterLoginService::class)->resetPassword($record);

                Notification::make()
                    ->title('Center password was reset.')
                    ->success()
                    ->send();
            });
    }

    public static function canDeleteSchemes(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User
            && app(OrganizationAccessService::class)->hasOrganizationWideAccess($actor);
    }

    public static function deleteUnusedScheme(mixed $schemeId): bool
    {
        $actor = auth()->user();

        abort_unless(
            $actor instanceof User && app(OrganizationAccessService::class)->hasOrganizationWideAccess($actor),
            403,
        );

        if (blank($schemeId)) {
            return false;
        }

        $scheme = Scheme::query()->find($schemeId);

        if (! $scheme instanceof Scheme) {
            return true;
        }

        return $scheme->deleteIfUnused();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCenters::route('/'),
            'create' => CreateCenter::route('/create'),
            'edit' => EditCenter::route('/{record}/edit'),
        ];
    }
}
