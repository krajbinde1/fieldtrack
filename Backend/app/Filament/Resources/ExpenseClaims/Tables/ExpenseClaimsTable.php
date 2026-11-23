<?php

namespace App\Filament\Resources\ExpenseClaims\Tables;

use App\Enums\ExpenseClaimStatus;
use App\Enums\ExpensePaymentStatus;
use App\Enums\ExpenseType;
use App\Filament\Support\EmployeeSelect;
use App\Services\AdminCenterContext;
use App\Services\OrganizationAccessService;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpenseClaimsTable
{
    public static function configure(Table $table): Table
    {
        $user = auth()->user();
        $access = app(OrganizationAccessService::class);

        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('claim_number')->label('Claim')->searchable(),
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('center.name')->label('Center')->searchable(),
                TextColumn::make('total_amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state): string => '₹'.number_format((float) $state, 2))
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof ExpenseClaimStatus ? $state->label() : (string) $state),
                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->formatStateUsing(fn ($state): string => $state instanceof ExpensePaymentStatus ? $state->label() : (string) $state),
                TextColumn::make('submitted_at')->dateTime('d M Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('center_id')
                    ->label('Center')
                    ->relationship(
                        'center',
                        'name',
                        fn ($query) => $user
                            ? app(AdminCenterContext::class)->scopeCenters($access->centerQuery($user), $user)
                            : $query->whereRaw('1 = 0'),
                    )
                    ->searchable()
                    ->preload(),
                SelectFilter::make('employee_id')
                    ->label('Employee')
                    ->relationship(
                        'employee',
                        'full_name',
                        fn ($query) => app(AdminCenterContext::class)->scopeEmployees($query),
                    )
                    ->tap(fn (SelectFilter $filter) => EmployeeSelect::applyRelationshipFilter($filter))
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')->options(collect(ExpenseClaimStatus::cases())->mapWithKeys(fn (ExpenseClaimStatus $status) => [$status->value => $status->label()])->all()),
                SelectFilter::make('payment_status')->options(collect(ExpensePaymentStatus::cases())->mapWithKeys(fn (ExpensePaymentStatus $status) => [$status->value => $status->label()])->all()),
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(ExpenseType::options())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('items', fn (Builder $items) => $items->where('type', $data['value']))
                        : $query),
                Filter::make('expense_date')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('to')->label('To'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereHas('items', fn (Builder $items) => $items->whereDate('expense_date', '>=', $date)))
                            ->when($data['to'] ?? null, fn (Builder $query, $date) => $query->whereHas('items', fn (Builder $items) => $items->whereDate('expense_date', '<=', $date)));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
