<?php

namespace App\Filament\Resources\CenterManagers;

use App\Enums\UserRole;
use App\Filament\Resources\CenterManagers\Pages\CreateCenterManager;
use App\Filament\Resources\CenterManagers\Pages\EditCenterManager;
use App\Filament\Resources\CenterManagers\Pages\ListCenterManagers;
use App\Filament\Support\CenterAssignmentSelect;
use App\Filament\Support\LoginIdInput;
use App\Models\User;
use App\Services\OrganizationAccessService;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class CenterManagerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'center-managers';

    protected static ?string $navigationLabel = 'Center Managers';

    protected static ?string $modelLabel = 'Center Manager';

    protected static string|\UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return (bool) ($user && app(OrganizationAccessService::class)->canManageCenterManagers($user));
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        return (bool) ($user && app(OrganizationAccessService::class)->canManageCenterManagers($user));
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->where('role', UserRole::CenterManager->value);
        $user = auth()->user();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdmin()) {
            return $query;
        }

        $centerIds = app(OrganizationAccessService::class)->visibleCenterIds($user) ?? [];

        return $query->whereHas('managedCenters', fn ($q) => $q->whereIn('centers.id', $centerIds));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            LoginIdInput::mobileFallback(),
            LoginIdInput::make(),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('password')->password()->revealable()->dehydrated(fn ($state) => filled($state))->required(fn (string $operation): bool => $operation === 'create')->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null),
            CenterAssignmentSelect::make('managedCenters'),
            Toggle::make('is_active')->default(true),
            Toggle::make('must_change_password')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('login_id')->label('Login ID'),
                TextColumn::make('managedCenters.name')->label('Centers')->badge(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCenterManagers::route('/'),
            'create' => CreateCenterManager::route('/create'),
            'edit' => EditCenterManager::route('/{record}/edit'),
        ];
    }
}
