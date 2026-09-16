<?php

namespace App\Filament\Resources\Employees;

use App\Filament\Concerns\ScopesRecordsByOrganization;
use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Support\LoginIdInput;
use App\Models\Employee;
use App\Services\OrganizationAccessService;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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

class EmployeeResource extends Resource
{
    use ScopesRecordsByOrganization;

    protected static ?string $model = Employee::class;

    protected static string|\UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isAdmin() || $user?->isDirector() || $user?->isProjectHead() || $user?->isCenterManager());
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return (bool) ($user && app(OrganizationAccessService::class)->canManageEmployees($user));
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        return (bool) ($user && app(OrganizationAccessService::class)->canManageEmployees($user, $record->center));
    }

    public static function form(Schema $schema): Schema
    {
        $access = app(OrganizationAccessService::class);
        $user = auth()->user();

        return $schema->components([
            Select::make('center_id')
                ->label('Center')
                ->relationship(
                    name: 'center',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn ($query) => $user ? $access->centerQuery($user) : $query->whereRaw('1=0'),
                )
                ->required()
                ->preload()
                ->searchable(),
            TextInput::make('full_name')->required()->maxLength(255),
            TextInput::make('mobile')->required()->length(10)->regex('/^[6-9][0-9]{9}$/')->unique(ignoreRecord: true),
            LoginIdInput::make()->dehydrated(false),
            TextInput::make('email')->email()->nullable(),
            TextInput::make('designation')->default('Employee')->required(),
            TextInput::make('department')->default('Field'),
            DatePicker::make('joining_date')->default(now()),
            TextInput::make('base_location')->maxLength(255),
            Toggle::make('status')->label('Active')->default(true),
            TextInput::make('login_password')
                ->label('Temporary password')
                ->password()
                ->revealable()
                ->dehydrated(false)
                ->helperText('Leave blank to use last 4 digits of mobile.')
                ->visibleOn('create'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee_code')->label('Code')->searchable()->sortable(),
                TextColumn::make('full_name')->searchable()->sortable(),
                TextColumn::make('center.project.name')->label('Project'),
                TextColumn::make('center.name')->label('Center'),
                TextColumn::make('mobile'),
                TextColumn::make('user.login_id')->label('Login ID'),
                IconColumn::make('status')->boolean()->label('Active'),
            ])
            ->filters([
                SelectFilter::make('center_id')->label('Center')->relationship('center', 'name')->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployees::route('/'),
            'create' => CreateEmployee::route('/create'),
            'edit' => EditEmployee::route('/{record}/edit'),
        ];
    }
}
