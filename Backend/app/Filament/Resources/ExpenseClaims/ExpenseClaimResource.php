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
use App\Services\Expenses\ExpenseClaimService;
use App\Support\AttendanceCalendar;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
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
                        ->required(),
                    TextInput::make('amount')
                        ->label('Amount')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->maxValue(9999999.99),
                    Textarea::make('description')
                        ->label('Description')
                        ->required()
                        ->columnSpanFull(),
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
