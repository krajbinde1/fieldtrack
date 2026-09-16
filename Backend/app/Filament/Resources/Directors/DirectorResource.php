<?php

namespace App\Filament\Resources\Directors;

use App\Enums\UserRole;
use App\Filament\Resources\Directors\Pages\CreateDirector;
use App\Filament\Resources\Directors\Pages\EditDirector;
use App\Filament\Resources\Directors\Pages\ListDirectors;
use App\Filament\Support\LoginIdInput;
use App\Models\User;
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

class DirectorResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'directors';

    protected static ?string $navigationLabel = 'Directors';

    protected static ?string $modelLabel = 'Director';

    protected static string|\UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 0;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', UserRole::Director->value);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            LoginIdInput::mobileFallback(),
            LoginIdInput::make(),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('password')->password()->revealable()->dehydrated(fn ($state) => filled($state))->required(fn (string $operation): bool => $operation === 'create')->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null),
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
                IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDirectors::route('/'),
            'create' => CreateDirector::route('/create'),
            'edit' => EditDirector::route('/{record}/edit'),
        ];
    }
}
