<?php

namespace App\Filament\Resources\TeamActivities\Tables;

use App\Filament\Resources\TeamActivities\TeamActivityResource;
use App\Models\Employee;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TeamActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Employees')
            ->description('Employees assigned to this center.')
            ->defaultSort('full_name')
            ->columns([
                TextColumn::make('full_name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('staff_role')
                    ->label('Role')
                    ->formatStateUsing(fn ($state, Employee $record): string => $record->staffRoleEnum()->label()),
            ])
            ->recordUrl(fn (Employee $record): string => TeamActivityResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make()->label('Activities'),
            ])
            ->emptyStateHeading('No employees assigned to this center');
    }
}
