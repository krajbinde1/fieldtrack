<?php

namespace App\Filament\Resources\Employees;

use App\Enums\CenterStaffRole;
use App\Filament\Concerns\ScopesRecordsByOrganization;
use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Models\Employee;
use App\Services\CenterStaffCredentialService;
use App\Services\OrganizationAccessService;
use App\Support\LoginId;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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

    protected static ?string $slug = 'center-users';

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $modelLabel = 'User';

    protected static ?string $pluralModelLabel = 'Users';

    protected static string|\UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 0;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function canAccess(): bool
    {
        return auth()->user()?->isCenterManager() === true;
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
        $centerIds = $user ? ($access->visibleCenterIds($user) ?? []) : [];
        $singleCenter = count($centerIds) === 1;

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
                ->searchable()
                ->default($singleCenter ? $centerIds[0] : null)
                ->disabled($singleCenter)
                ->dehydrated(),
            TextInput::make('full_name')->label('Name')->required()->maxLength(255),
            TextInput::make('mobile')
                ->label('Mobile Number')
                ->required()
                ->length(10)
                ->regex('/^[6-9][0-9]{9}$/')
                ->unique(ignoreRecord: true)
                ->helperText(fn (string $operation): string => $operation === 'create'
                    ? 'Login ID will be this number. Default password is the last 4 digits. You do not need to enter a Login ID or Password.'
                    : 'Changing this number does not change the password. Use Reset Password to set it to the last 4 digits.'),
            TextInput::make('email')->label('Email (optional)')->email()->nullable(),
            Select::make('staff_role')
                ->label('Login Role')
                ->options(CenterStaffRole::options())
                ->required()
                ->native(false),
            Toggle::make('status')->label('Active')->default(true),
            Section::make('Organization')
                ->description('Linked automatically from the Center Manager assignment.')
                ->schema([
                    TextInput::make('scheme_name')
                        ->label('Scheme / Project')
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('center_manager_name')
                        ->label('Center Manager')
                        ->disabled()
                        ->dehydrated(false),
                ])
                ->visibleOn('edit')
                ->columns(2)
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')->label('Name')->searchable()->sortable(),
                TextColumn::make('mobile')->label('Mobile'),
                TextColumn::make('email')->toggleable(),
                TextColumn::make('user.login_id')->label('Login ID')->searchable(),
                TextColumn::make('staff_role')
                    ->label('Login Role')
                    ->formatStateUsing(fn (?string $state): string => CenterStaffRole::tryFromMixed($state)->label())
                    ->badge(),
                TextColumn::make('center.name')->label('Center'),
                IconColumn::make('status')->boolean()->label('Active'),
            ])
            ->filters([
                SelectFilter::make('staff_role')->label('Login Role')->options(CenterStaffRole::options()),
            ])
            ->recordActions([
                EditAction::make(),
                self::resetPasswordAction(),
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

    public static function resetPasswordAction(): Action
    {
        return Action::make('resetPassword')
            ->label('Reset Password')
            ->icon('heroicon-o-key')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Reset Password')
            ->modalDescription('This sets the password to the last 4 digits of the current mobile number. The Login ID is not changed.')
            ->modalSubmitActionLabel('Reset Password')
            ->visible(function (?Employee $record): bool {
                $user = auth()->user();

                return $record !== null
                    && $record->user !== null
                    && $user !== null
                    && app(OrganizationAccessService::class)->canManageEmployees($user, $record->center);
            })
            ->action(function (Employee $record): void {
                $password = app(CenterStaffCredentialService::class)->resetPassword($record->loadMissing('user'));

                Notification::make()
                    ->title(LoginId::defaultPasswordResetMessage($password))
                    ->success()
                    ->persistent()
                    ->send();
            });
    }
}
