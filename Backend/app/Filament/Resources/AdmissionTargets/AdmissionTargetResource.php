<?php

namespace App\Filament\Resources\AdmissionTargets;

use App\Filament\Concerns\ScopesRecordsByOrganization;
use App\Filament\Resources\AdmissionTargets\Pages\CreateAdmissionTarget;
use App\Filament\Resources\AdmissionTargets\Pages\EditAdmissionTarget;
use App\Filament\Resources\AdmissionTargets\Pages\ListAdmissionTargets;
use App\Filament\Resources\AdmissionTargets\Pages\ViewAdmissionTarget;
use App\Filament\Resources\AdmissionTargets\Schemas\AdmissionTargetForm;
use App\Filament\Resources\AdmissionTargets\Schemas\AdmissionTargetInfolist;
use App\Filament\Resources\AdmissionTargets\Tables\AdmissionTargetsTable;
use App\Models\AdmissionTarget;
use App\Services\OrganizationAccessService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AdmissionTargetResource extends Resource
{
    use ScopesRecordsByOrganization;

    protected static ?string $model = AdmissionTarget::class;

    protected static ?string $slug = 'admission-targets';

    protected static string|\UnitEnum|null $navigationGroup = 'Admissions';

    protected static ?string $navigationLabel = 'Admission Targets';

    protected static ?string $modelLabel = 'Admission Target';

    protected static ?string $pluralModelLabel = 'Admission Targets';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $recordTitleAttribute = 'id';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isAdmin() || $user?->isDirector() || $user?->isProjectHead() || $user?->isCenterManager());
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isCenterManager() === true;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();
        if (! $user || ! $record instanceof AdmissionTarget || $record->parent_id !== null) {
            return false;
        }

        $record->loadMissing('employee');

        return $record->employee !== null
            && app(OrganizationAccessService::class)->canAssignAdmissionTarget($user, $record->employee);
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canView($record): bool
    {
        $user = auth()->user();
        if (! $user || ! $record instanceof AdmissionTarget) {
            return false;
        }

        return app(OrganizationAccessService::class)->canViewAdmissionTarget($user, $record);
    }

    public static function form(Schema $schema): Schema
    {
        return AdmissionTargetForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AdmissionTargetInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdmissionTargetsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdmissionTargets::route('/'),
            'create' => CreateAdmissionTarget::route('/create'),
            'view' => ViewAdmissionTarget::route('/{record}'),
            'edit' => EditAdmissionTarget::route('/{record}/edit'),
        ];
    }
}
