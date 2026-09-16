<?php

namespace App\Filament\Resources\OrgUsers;

use App\Enums\UserRole;
use App\Filament\Resources\OrgUsers\Pages\CreateOrgUser;
use App\Filament\Resources\OrgUsers\Pages\EditOrgUser;
use App\Filament\Resources\OrgUsers\Pages\ListOrgUsers;
use App\Filament\Support\LoginIdInput;
use App\Models\User;
use App\Services\OrganizationAccessService;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class OrgUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'users';

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $modelLabel = 'User';

    protected static ?string $pluralModelLabel = 'Users';

    protected static string|\UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 0;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user && app(OrganizationAccessService::class)->canAccessOrgUsers($user));
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return (bool) ($user && app(OrganizationAccessService::class)->creatableOrgUserRoles($user) !== []);
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        return (bool) ($user && $record instanceof User && app(OrganizationAccessService::class)->canManageOrgUser($user, $record));
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['directedProjects', 'headedProjects', 'managedCenters']);
        $user = auth()->user();
        $access = app(OrganizationAccessService::class);

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        $roles = $access->visibleOrgUserRoles($user);
        $query->whereIn('role', $roles);

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isDirector()) {
            $projectIds = $access->visibleProjectIds($user) ?? [];
            $centerIds = $access->visibleCenterIds($user) ?? [];

            return $query->where(function (Builder $inner) use ($projectIds, $centerIds): void {
                $inner->where(function (Builder $ph) use ($projectIds): void {
                    $ph->where('role', UserRole::ProjectHead->value)
                        ->whereHas('headedProjects', fn ($q) => $q->whereIn('projects.id', $projectIds));
                })->orWhere(function (Builder $cm) use ($centerIds): void {
                    $cm->where('role', UserRole::CenterManager->value)
                        ->whereHas('managedCenters', fn ($q) => $q->whereIn('centers.id', $centerIds));
                });
            });
        }

        $centerIds = $access->visibleCenterIds($user) ?? [];

        return $query->whereHas('managedCenters', fn ($q) => $q->whereIn('centers.id', $centerIds));
    }

    public static function form(Schema $schema): Schema
    {
        $access = app(OrganizationAccessService::class);
        $user = auth()->user();
        $roleOptions = [];
        foreach ($user ? $access->creatableOrgUserRoles($user) : [] as $role) {
            $roleOptions[$role] = UserRole::tryFromMixed($role)->label();
        }

        return $schema->components([
            Select::make('role')
                ->label('Login Role')
                ->options(fn (): array => $roleOptions)
                ->required()
                ->live()
                ->disabledOn('edit'),
            TextInput::make('name')->required()->maxLength(255),
            LoginIdInput::mobileFallback(),
            LoginIdInput::make(),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('password')
                ->password()
                ->revealable()
                ->dehydrated(fn ($state) => filled($state))
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null),
            Select::make('directedProjects')
                ->label('Assigned Project(s)')
                ->relationship('directedProjects', 'name')
                ->multiple()
                ->preload()
                ->searchable()
                ->required()
                ->visible(fn ($get): bool => $get('role') === UserRole::Director->value),
            Select::make('headedProjects')
                ->label('Assigned Project(s)')
                ->relationship(
                    name: 'headedProjects',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn ($query) => $user ? $access->projectQuery($user) : $query->whereRaw('1=0'),
                )
                ->multiple()
                ->preload()
                ->searchable()
                ->required()
                ->visible(fn ($get): bool => $get('role') === UserRole::ProjectHead->value)
                ->saveRelationshipsUsing(function (User $record, $state) use ($access, $user): void {
                    $visible = $user ? $access->visibleProjectIds($user) : [];
                    if ($visible === null) {
                        $record->headedProjects()->sync($state ?? []);

                        return;
                    }

                    $keep = $record->headedProjects()
                        ->whereNotIn('projects.id', $visible)
                        ->pluck('projects.id')
                        ->all();
                    $record->headedProjects()->sync(array_values(array_unique(array_merge($keep, $state ?? []))));
                }),
            Select::make('managedCenters')
                ->label('Assigned Center(s)')
                ->relationship(
                    name: 'managedCenters',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn ($query) => $user ? $access->centerQuery($user) : $query->whereRaw('1=0'),
                )
                ->multiple()
                ->preload()
                ->searchable()
                ->required()
                ->visible(fn ($get): bool => $get('role') === UserRole::CenterManager->value),
            Toggle::make('is_active')->label('Active')->default(true),
            Toggle::make('must_change_password')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('role')
                    ->label('Login Role')
                    ->formatStateUsing(fn (?string $state): string => UserRole::tryFromMixed($state)->label())
                    ->badge(),
                TextColumn::make('login_id')->label('Login ID')->searchable(),
                TextColumn::make('email')->toggleable(),
                TextColumn::make('assignments')
                    ->label('Assignment')
                    ->state(function (User $record): string {
                        if ($record->isDirector()) {
                            return $record->directedProjects->pluck('name')->join(', ') ?: '—';
                        }
                        if ($record->isProjectHead()) {
                            return $record->headedProjects->pluck('name')->join(', ') ?: '—';
                        }
                        if ($record->isCenterManager()) {
                            return $record->managedCenters->pluck('name')->join(', ') ?: '—';
                        }

                        return '—';
                    }),
                IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->filters([
                SelectFilter::make('role')->options([
                    UserRole::Director->value => UserRole::Director->label(),
                    UserRole::ProjectHead->value => UserRole::ProjectHead->label(),
                    UserRole::CenterManager->value => UserRole::CenterManager->label(),
                ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (User $record): bool => static::canEdit($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrgUsers::route('/'),
            'create' => CreateOrgUser::route('/create'),
            'edit' => EditOrgUser::route('/{record}/edit'),
        ];
    }
}
