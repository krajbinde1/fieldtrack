<?php

namespace App\Filament\Resources\ExpenseClaims;

use App\Enums\CenterExpenseCategory;
use App\Enums\ExpenseClaimStatus;
use App\Filament\Concerns\ScopesRecordsByOrganization;
use App\Filament\Resources\ExpenseClaims\Pages\CreateExpenseClaim;
use App\Filament\Resources\ExpenseClaims\Pages\EditExpenseClaim;
use App\Filament\Resources\ExpenseClaims\Pages\ListExpenseClaims;
use App\Filament\Resources\ExpenseClaims\Pages\ViewExpenseClaim;
use App\Filament\Resources\ExpenseClaims\Schemas\ExpenseClaimInfolist;
use App\Filament\Resources\ExpenseClaims\Tables\ExpenseClaimsTable;
use App\Models\ExpenseClaim;
use App\Models\User;
use App\Services\AdminCenterContext;
use App\Services\Expenses\ExpenseClaimService;
use App\Support\AttendanceCalendar;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ExpenseClaimResource extends Resource
{
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

        return $user instanceof User
            && ($user->isAdmin() || $user->isDirector() || $user->isCenterCoordinator() || $user->isCenterManager());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /**
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        return array_map(
            fn (NavigationItem $item): NavigationItem => $item->visible(function (): bool {
                $user = auth()->user();
                if (! $user instanceof User || ! static::canAccess()) {
                    return false;
                }

                $centerOpen = app(AdminCenterContext::class)->showsCenterModuleNavigation($user);

                return $user->isCenterManager() ? $centerOpen : ! $centerOpen;
            }),
            parent::getNavigationItems(),
        );
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isCenterManager() === true;
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->isCenterManager()
            && $record instanceof ExpenseClaim
            && (int) $record->created_by_user_id === (int) $user->id
            && $record->status === ExpenseClaimStatus::Draft;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Repeater::make('items')
                ->label('Expenses')
                ->addActionLabel('Add Another Expense')
                ->minItems(1)
                ->schema([
                    DatePicker::make('expense_date')
                        ->label('Expense Date')
                        ->required()
                        ->maxDate(AttendanceCalendar::today()),
                    Select::make('type')
                        ->label('Category')
                        ->options(CenterExpenseCategory::options())
                        ->required()
                        ->live(),
                    Select::make('travel_mode')
                        ->label('Travelling type')
                        ->options([
                            'self' => 'Self Travelling',
                            'public' => 'Public Transport',
                        ])
                        ->required(fn (Get $get): bool => self::isTravelCategory($get('type')))
                        ->visible(fn (Get $get): bool => self::isTravelCategory($get('type')))
                        ->live(),
                    TextInput::make('amount')
                        ->label('Amount')
                        ->numeric()
                        ->required(fn (Get $get): bool => $get('travel_mode') !== 'self')
                        ->minValue(0.01)
                        ->maxValue(9999999.99)
                        ->helperText('Self Travelling amount is calculated from the recorded GPS KM and the employee KM rate.'),
                    Textarea::make('description')
                        ->label('Description')
                        ->required()
                        ->columnSpanFull(),
                    TextInput::make('travel_from')
                        ->label('Travel from')
                        ->required(fn (Get $get): bool => $get('travel_mode') === 'public')
                        ->visible(fn (Get $get): bool => self::isTravelCategory($get('type'))),
                    TextInput::make('travel_to')
                        ->label('Travel to')
                        ->required(fn (Get $get): bool => $get('travel_mode') === 'public')
                        ->visible(fn (Get $get): bool => self::isTravelCategory($get('type'))),
                    FileUpload::make('receipt')
                        ->label('Bill Upload')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                        ->maxSize(5120)
                        ->storeFiles(false)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
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

    private static function isTravelCategory(mixed $type): bool
    {
        return in_array($type, ['travelling', CenterExpenseCategory::TravelConveyance->value], true);
    }

    public static function table(Table $table): Table
    {
        return ExpenseClaimsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpenseClaims::route('/'),
            'create' => CreateExpenseClaim::route('/create'),
            'view' => ViewExpenseClaim::route('/{record}'),
            'edit' => EditExpenseClaim::route('/{record}/edit'),
        ];
    }
}
