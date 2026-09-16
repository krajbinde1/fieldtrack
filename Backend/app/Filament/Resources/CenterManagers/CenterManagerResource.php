<?php

namespace App\Filament\Resources\CenterManagers;

use App\Enums\UserRole;
use App\Filament\Resources\CenterManagers\Pages\CreateCenterManager;
use App\Filament\Resources\CenterManagers\Pages\EditCenterManager;
use App\Filament\Resources\CenterManagers\Pages\ListCenterManagers;
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

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isDirector() || $user?->isProjectHead());
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->where('role', UserRole::CenterManager->value);
        $user = auth()->user();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isDirector()) {
            return $query;
        }

        $centerIds = app(OrganizationAccessService::class)->visibleCenterIds($user) ?? [];

        return $query->whereHas('managedCenters', fn ($q) => $q->whereIn('centers.id', $centerIds));
    }

    public static function form(Schema $schema): Schema
    {
        $access = app(OrganizationAccessService::class);
        $user = auth()->user();

        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('login_id')->label('Login ID')->required()->maxLength(32)->unique(ignoreRecord: true)->regex('/^[A-Za-z0-9]+$/'),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('password')->password()->revealable()->dehydrated(fn ($state) => filled($state))->required(fn (string $operation): bool => $operation === 'create')->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null),
            Select::make('managedCenters')
                ->label('Assigned Center(s)')
                ->relationship(
                    name: 'managedCenters',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn ($query) => $user ? $access->centerQuery($user) : $query->whereRaw('1=0'),
                )
                ->multiple()
                ->preload()
                ->searchable(),
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
