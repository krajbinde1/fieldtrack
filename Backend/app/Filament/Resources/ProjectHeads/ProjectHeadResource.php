<?php

namespace App\Filament\Resources\ProjectHeads;

use App\Enums\UserRole;
use App\Filament\Resources\ProjectHeads\Pages\CreateProjectHead;
use App\Filament\Resources\ProjectHeads\Pages\EditProjectHead;
use App\Filament\Resources\ProjectHeads\Pages\ListProjectHeads;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class ProjectHeadResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'project-heads';

    protected static ?string $navigationLabel = 'Project Heads';

    protected static ?string $modelLabel = 'Project Head';

    protected static string|\UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user && app(OrganizationAccessService::class)->canManageProjectHeads($user));
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->where('role', UserRole::ProjectHead->value);
        $user = auth()->user();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdmin()) {
            return $query;
        }

        $projectIds = app(OrganizationAccessService::class)->visibleProjectIds($user) ?? [];

        return $query->whereHas('headedProjects', fn ($q) => $q->whereIn('projects.id', $projectIds));
    }

    public static function form(Schema $schema): Schema
    {
        $access = app(OrganizationAccessService::class);
        $user = auth()->user();

        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            LoginIdInput::mobileFallback(),
            LoginIdInput::make(),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('password')->password()->revealable()->dehydrated(fn ($state) => filled($state))->required(fn (string $operation): bool => $operation === 'create')->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null),
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
                TextColumn::make('headedProjects.name')->label('Projects')->badge(),
                IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjectHeads::route('/'),
            'create' => CreateProjectHead::route('/create'),
            'edit' => EditProjectHead::route('/{record}/edit'),
        ];
    }
}
