<?php

namespace App\Filament\Resources\OrgUsers;

use App\Enums\UserRole;
use App\Filament\Resources\OrgUsers\Pages\CreateOrgUser;
use App\Filament\Resources\OrgUsers\Pages\EditOrgUser;
use App\Filament\Resources\OrgUsers\Pages\ListOrgUsers;
use App\Filament\Support\CenterAssignmentSelect;
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
        $query = parent::getEloquentQuery()->with(['headedCenters.scheme', 'managedCenters.scheme']);
        $user = auth()->user();
        $access = app(OrganizationAccessService::class);

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        $roles = $access->visibleOrgUserRoles($user);
        $query->whereIn('role', $roles);

        if ($user->isAdmin() || $user->isDirector()) {
            return $query;
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
            LoginIdInput::emailWithLoginIdSync(),
            LoginIdInput::fromEmail(),
            TextInput::make('password')
                ->password()
                ->revealable()
                ->dehydrated(fn ($state) => filled($state))
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null),
            CenterAssignmentSelect::make()
                ->visible(fn ($get): bool => $get('role') === UserRole::ProjectHead->value),
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
                            return 'All organization';
                        }
                        if ($record->isProjectHead()) {
                            return $record->headedCenters
                                ->map(fn ($center) => $center->assignmentLabel())
                                ->join(', ') ?: '—';
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
