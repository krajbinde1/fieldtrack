<?php

namespace App\Filament\Resources\Centers;

use App\Filament\Concerns\ScopesRecordsByOrganization;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Centers\Pages\CreateCenter;
use App\Filament\Resources\Centers\Pages\EditCenter;
use App\Filament\Resources\Centers\Pages\ListCenters;
use App\Filament\Support\CenterManagerSelect;
use App\Models\Center;
use App\Models\Scheme;
use App\Models\User;
use App\Services\AdminCenterContext;
use App\Services\OrganizationAccessService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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

        return (bool) ($user && app(OrganizationAccessService::class)->canManageCenters($user, $record));
    }

    public static function form(Schema $schema): Schema
    {
        $access = app(OrganizationAccessService::class);
        $user = auth()->user();

        return $schema->components([
            Select::make('scheme_id')
                ->label('Select Scheme')
                ->relationship(
                    name: 'scheme',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn ($query) => $user ? $access->schemeQuery($user) : $query->whereRaw('1=0'),
                )
                ->required()
                ->preload()
                ->searchable()
                ->disabled(fn (): bool => auth()->user()?->isCenterManager() ?? false)
                ->createOptionModalHeading('Create New Scheme')
                ->createOptionForm([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('code')->maxLength(64)->unique(ignoreRecord: true),
                    Textarea::make('description')->columnSpanFull(),
                    Toggle::make('is_active')->label('Active')->default(true),
                ])
                ->createOptionUsing(function (array $data) use ($access): int {
                    $actor = auth()->user();
                    abort_unless($actor && $access->canManageSchemes($actor), 403);

                    $scheme = Scheme::query()->create([
                        'name' => $data['name'],
                        'code' => filled($data['code'] ?? null) ? $data['code'] : null,
                        'description' => filled($data['description'] ?? null) ? $data['description'] : null,
                        'is_active' => (bool) ($data['is_active'] ?? true),
                    ]);

                    return (int) $scheme->getKey();
                })
                ->createOptionAction(fn (Action $action): Action => $action
                    ->label('+ Create New Scheme')
                    ->iconButton(false)
                    ->link()
                    ->visible(fn (): bool => ($actor = auth()->user()) !== null && $access->canManageSchemes($actor))
                ),
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('address')->maxLength(255),
            CenterManagerSelect::make(),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('scheme.name')->label('Scheme / Project')->searchable()->sortable(),
                TextColumn::make('code')->label('Center Code')->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('centerManagers.name')->label('Center Manager(s)')->badge()->placeholder('—'),
                TextColumn::make('employees_count')->counts('employees')->label('Employees'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('scheme_id')->label('Scheme / Project')->relationship('scheme', 'name')->preload(),
            ])
            ->recordActions([
                Action::make('openDashboard')
                    ->label('Open Dashboard')
                    ->visible(fn (): bool => auth()->user()?->isAdmin() === true)
                    ->action(function (Center $record) {
                        $user = auth()->user();
                        abort_unless($user instanceof User && $user->isAdmin(), 403);

                        app(AdminCenterContext::class)->select($user, (int) $record->id);

                        return redirect()->to(Dashboard::getUrl());
                    }),
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
