<?php

namespace App\Filament\Resources\TeamActivities;

use App\Filament\Concerns\RequiresSelectedAdminCenter;
use App\Filament\Concerns\ScopesRecordsByOrganization;
use App\Filament\Resources\TeamActivities\Pages\ListTeamActivities;
use App\Filament\Resources\TeamActivities\Pages\ViewTeamActivity;
use App\Filament\Resources\TeamActivities\Tables\TeamActivitiesTable;
use App\Models\Employee;
use App\Models\User;
use App\Services\OrganizationAccessService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TeamActivityResource extends Resource
{
    use RequiresSelectedAdminCenter;
    use ScopesRecordsByOrganization;

    protected static ?string $model = Employee::class;

    protected static ?string $slug = 'team-activities';

    protected static string|\UnitEnum|null $navigationGroup = 'Field Operations';

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Team Activity';

    protected static ?string $modelLabel = 'Team Activity';

    protected static ?string $pluralModelLabel = 'Team Activity';

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && ($user->isAdmin() || $user->isDirector() || $user->isCenterCoordinator() || $user->isCenterManager());
    }

    public static function canView(Model $record): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $record instanceof Employee
            && static::canAccess()
            && app(OrganizationAccessService::class)->canViewEmployee($user, $record);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return TeamActivitiesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeamActivities::route('/'),
            'view' => ViewTeamActivity::route('/{record}'),
        ];
    }
}
