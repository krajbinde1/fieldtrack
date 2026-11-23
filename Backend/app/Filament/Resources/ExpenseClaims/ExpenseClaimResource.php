<?php

namespace App\Filament\Resources\ExpenseClaims;

use App\Filament\Concerns\RequiresSelectedAdminCenter;
use App\Filament\Concerns\ScopesRecordsByOrganization;
use App\Filament\Resources\ExpenseClaims\Pages\ListExpenseClaims;
use App\Filament\Resources\ExpenseClaims\Pages\ViewExpenseClaim;
use App\Filament\Resources\ExpenseClaims\Schemas\ExpenseClaimInfolist;
use App\Filament\Resources\ExpenseClaims\Tables\ExpenseClaimsTable;
use App\Models\ExpenseClaim;
use App\Services\Expenses\ExpenseClaimService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ExpenseClaimResource extends Resource
{
    use RequiresSelectedAdminCenter;
    use ScopesRecordsByOrganization;

    protected static ?string $model = ExpenseClaim::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Expenses';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Expenses';

    protected static ?string $modelLabel = 'Expense';

    protected static ?string $pluralModelLabel = 'Expenses';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isAdmin() || $user?->isDirector() || $user?->isCenterCoordinator() || $user?->isCenterManager());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = app(ExpenseClaimService::class)->pendingCountFor(auth()->user());

        return $count > 0 ? (string) $count : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return ExpenseClaimInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExpenseClaimsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpenseClaims::route('/'),
            'view' => ViewExpenseClaim::route('/{record}'),
        ];
    }
}
