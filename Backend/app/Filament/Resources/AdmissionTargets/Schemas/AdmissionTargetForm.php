<?php

namespace App\Filament\Resources\AdmissionTargets\Schemas;

use App\Enums\AdmissionTargetType;
use App\Filament\Support\EmployeeSelect;
use App\Services\OrganizationAccessService;
use App\Support\AttendanceCalendar;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AdmissionTargetForm
{
    public static function configure(Schema $schema): Schema
    {
        $access = app(OrganizationAccessService::class);
        $user = auth()->user();

        return $schema->components([
            Select::make('employee_id')
                ->label('Employee')
                ->relationship(
                    name: 'employee',
                    titleAttribute: 'full_name',
                    modifyQueryUsing: fn ($query) => $user
                        ? $access->employeeQuery($user)->where('status', true)->orderBy('full_name')
                        : $query->whereRaw('1=0'),
                )
                ->tap(fn (Select $select) => EmployeeSelect::applyRelationshipSelect($select))
                ->required()
                ->preload()
                ->searchable()
                ->disabledOn('edit')
                ->dehydrated(),
            Select::make('target_type')
                ->label('Target type')
                ->options(AdmissionTargetType::options())
                ->required()
                ->native(false)
                ->live(),
            TextInput::make('target_count')
                ->label('Target count')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->required(),
            DatePicker::make('period')
                ->label('Target period')
                ->required()
                ->native(false)
                ->format('Y-m-d')
                ->displayFormat('d M Y')
                ->timezone(AttendanceCalendar::TIMEZONE)
                ->helperText(function (Get $get): string {
                    return $get('target_type') === AdmissionTargetType::Monthly->value
                        ? 'Pick any date in the target month. Weekly splits are created automatically.'
                        : 'Pick any date in the target week (Monday–Sunday).';
                }),
        ]);
    }
}
