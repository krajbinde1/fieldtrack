<?php

namespace App\Filament\Resources\Admissions\Tables;

use App\Enums\AdmissionStatus;
use App\Filament\Support\EmployeeSelect;
use App\Models\Admission;
use App\Services\OrganizationAccessService;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdmissionsTable
{
    public static function configure(Table $table): Table
    {
        $user = auth()->user();
        $access = app(OrganizationAccessService::class);

        return $table
            ->heading('Admissions')
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('full_name')
                    ->label('Applicant')
                    ->searchable(['full_name', 'first_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('scheme.name')->label('Scheme / Project')->searchable()->toggleable(),
                TextColumn::make('center.name')->label('Center')->toggleable(),
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->formatStateUsing(fn (Admission $record): string => $record->employee?->displayLabel() ?? '-')
                    ->toggleable(),
                TextColumn::make('district.name')->label('District')->toggleable(),
                TextColumn::make('taluka.name')->label('Taluka')->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof AdmissionStatus ? $state->label() : ucfirst((string) $state))
                    ->color(fn ($state): string => ($state instanceof AdmissionStatus ? $state : AdmissionStatus::tryFrom((string) $state)) === AdmissionStatus::Submitted ? 'success' : 'warning'),
                TextColumn::make('submitted_at')->dateTime('d M Y')->placeholder('-')->sortable(),
                TextColumn::make('created_at')->dateTime('d M Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('scheme_id')
                    ->label('Scheme / Project')
                    ->relationship(
                        name: 'scheme',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $user ? $access->schemeQuery($user) : $query->whereRaw('1=0'),
                    )
                    ->preload()
                    ->searchable(),
                SelectFilter::make('center_id')
                    ->label('Center')
                    ->relationship(
                        name: 'center',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $user ? $access->centerQuery($user) : $query->whereRaw('1=0'),
                    )
                    ->preload()
                    ->searchable(),
                SelectFilter::make('employee_id')
                    ->label('Employee')
                    ->relationship('employee', 'full_name')
                    ->tap(fn (SelectFilter $filter) => EmployeeSelect::applyRelationshipFilter($filter))
                    ->preload(),
                SelectFilter::make('district_id')
                    ->label('District')
                    ->relationship('district', 'name')
                    ->preload()
                    ->searchable(),
                SelectFilter::make('taluka_id')
                    ->label('Taluka')
                    ->relationship('taluka', 'name')
                    ->preload()
                    ->searchable(),
                SelectFilter::make('status')
                    ->options(AdmissionStatus::options()),
                Filter::make('date_range')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('To'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $inner, $date) => $inner->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $inner, $date) => $inner->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
