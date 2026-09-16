<?php

namespace App\Filament\Resources\ProjectHeads;

use App\Enums\UserRole;
use App\Filament\Resources\ProjectHeads\Pages\CreateProjectHead;
use App\Filament\Resources\ProjectHeads\Pages\EditProjectHead;
use App\Filament\Resources\ProjectHeads\Pages\ListProjectHeads;
use App\Models\User;
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
        return auth()->user()?->isDirector() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', UserRole::ProjectHead->value);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('login_id')->label('Login ID')->required()->maxLength(32)->unique(ignoreRecord: true)->regex('/^[A-Za-z0-9]+$/'),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('password')->password()->revealable()->dehydrated(fn ($state) => filled($state))->required(fn (string $operation): bool => $operation === 'create')->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null),
            Select::make('headedProjects')->label('Assigned Project(s)')->relationship('headedProjects', 'name')->multiple()->preload()->searchable(),
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
