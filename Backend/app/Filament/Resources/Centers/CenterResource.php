<?php

namespace App\Filament\Resources\Centers;

use App\Filament\Concerns\ScopesRecordsByOrganization;
use App\Filament\Resources\Centers\Pages\CreateCenter;
use App\Filament\Resources\Centers\Pages\EditCenter;
use App\Filament\Resources\Centers\Pages\ListCenters;
use App\Models\Center;
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

class CenterResource extends Resource
{
    use ScopesRecordsByOrganization;

    protected static ?string $model = Center::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Organization';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isAdmin() || $user?->isDirector() || $user?->isProjectHead() || $user?->isCenterManager());
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return (bool) ($user && app(OrganizationAccessService::class)->canManageCenters($user));
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        return (bool) ($user && app(OrganizationAccessService::class)->canManageCenters($user, $record->project));
    }

    public static function form(Schema $schema): Schema
    {
        $access = app(OrganizationAccessService::class);
        $user = auth()->user();

        return $schema->components([
            Select::make('project_id')
                ->label('Project')
                ->relationship(
                    name: 'project',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn ($query) => $user ? $access->projectQuery($user) : $query->whereRaw('1=0'),
                )
                ->required()
                ->preload()
                ->searchable()
                ->disabled(fn (): bool => auth()->user()?->isCenterManager() ?? false),
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('code')->required()->maxLength(32),
            TextInput::make('address')->maxLength(255),
            Select::make('centerManagers')
                ->label('Center Manager(s)')
                ->relationship(
                    name: 'centerManagers',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn ($query) => $query->where('role', 'center_manager')->where('is_active', true),
                )
                ->multiple()
                ->preload()
                ->searchable()
                ->visible(fn (): bool => ! (auth()->user()?->isCenterManager() ?? false)),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('project.name')->label('Project')->searchable()->sortable(),
                TextColumn::make('code')->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('centerManagers.name')->label('Managers')->badge(),
                TextColumn::make('employees_count')->counts('employees')->label('Employees'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('project_id')->label('Project')->relationship('project', 'name')->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
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
