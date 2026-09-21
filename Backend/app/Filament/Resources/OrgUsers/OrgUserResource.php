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
        $query = parent::getEloquentQuery()->with(['headedCenters', 'managedCenters', 'employee.center']);
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
            CenterAssignmentSelect::make('managedCenters')
                ->visible(fn ($get): bool => $get('role') === UserRole::CenterManager->value),
            Toggle::make('is_active')->label('Active')->default(true),
            Toggle::make('must_change_password')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        $access = app(OrganizationAccessService::class);
        $user = auth()->user();

        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('role')
                    ->label('Login Role')
                    ->formatStateUsing(fn (?string $state): string => UserRole::tryFromMixed($state)->label())
                    ->badge(),
                TextColumn::make('assigned_centers')
                    ->label('Center')
                    ->badge()
                    ->placeholder('—')
                    ->state(fn (User $record): array => $record->assignedCenterNames())
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return self::constrainUsersByCenterName($query, $search);
                    }),
                TextColumn::make('login_id')->label('Login ID')->searchable(),
                TextColumn::make('email')->toggleable(),
                IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->filters([
                SelectFilter::make('role')->options([
                    UserRole::Director->value => UserRole::Director->label(),
                    UserRole::ProjectHead->value => UserRole::ProjectHead->label(),
                    UserRole::CenterManager->value => UserRole::CenterManager->label(),
                ]),
                SelectFilter::make('center')
                    ->label('Center')
                    ->options(function () use ($access, $user): array {
                        if ($user === null) {
                            return [];
                        }

                        return $access->centerQuery($user)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        $centerId = (int) ($data['value'] ?? 0);
                        if ($centerId < 1) {
                            return $query;
                        }

                        return self::constrainUsersByCenterId($query, $centerId);
                    })
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (User $record): bool => static::canEdit($record)),
            ]);
    }

    public static function constrainUsersByCenterId(Builder $query, int $centerId): Builder
    {
        return $query->where(function (Builder $inner) use ($centerId): void {
            $inner->whereHas('headedCenters', fn (Builder $centers) => $centers->where('centers.id', $centerId))
                ->orWhereHas('managedCenters', fn (Builder $centers) => $centers->where('centers.id', $centerId))
                ->orWhereHas('employee', fn (Builder $employee) => $employee->where('center_id', $centerId));
        });
    }

    public static function constrainUsersByCenterName(Builder $query, string $search): Builder
    {
        $term = '%'.$search.'%';

        return $query->where(function (Builder $inner) use ($term): void {
            $inner->whereHas('headedCenters', fn (Builder $centers) => $centers->where('centers.name', 'like', $term))
                ->orWhereHas('managedCenters', fn (Builder $centers) => $centers->where('centers.name', 'like', $term))
                ->orWhereHas('employee.center', fn (Builder $centers) => $centers->where('name', 'like', $term));
        });
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
