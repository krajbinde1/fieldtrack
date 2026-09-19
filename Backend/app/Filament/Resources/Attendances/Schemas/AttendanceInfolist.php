<?php

namespace App\Filament\Resources\Attendances\Schemas;

use App\Models\Attendance;
use App\Services\Attendance\AttendanceStatusCalculator;
use App\Support\AttendanceCalendar;
use App\Support\PublicStorage;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttendanceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Attendance details')->columns(3)->schema([
                    TextEntry::make('employee.full_name')
                        ->label('Employee')
                        ->formatStateUsing(fn (Attendance $record): string => $record->employee?->displayLabel() ?? '-'),
                    TextEntry::make('employee.center.scheme.name')
                        ->label('Scheme / Project')
                        ->placeholder('-'),
                    TextEntry::make('employee.center.name')
                        ->label('Center')
                        ->placeholder('-'),
                    TextEntry::make('attendance_date')
                        ->label('Attendance Date')
                        ->date('d M Y'),
                    TextEntry::make('attendance_status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn ($state): string => Attendance::ATTENDANCE_STATUS_LABELS[(string) $state] ?? (string) $state),
                    TextEntry::make('approval_status')->label('Approval Status')->badge(),
                    TextEntry::make('punch_in_time')
                        ->label('Punch In Time')
                        ->formatStateUsing(fn (Attendance $record): string => self::formatDateTime($record->punchInAt()))
                        ->placeholder('-'),
                    TextEntry::make('punch_out_time')
                        ->label('Punch Out Time')
                        ->formatStateUsing(fn (Attendance $record): string => self::formatDateTime($record->punchOutAt()))
                        ->placeholder('-'),
                    TextEntry::make('working_hours')
                        ->label('Working Hours')
                        ->formatStateUsing(fn (Attendance $record): string => app(AttendanceStatusCalculator::class)->formatWorkingHoursLabel($record))
                        ->placeholder('-'),
                    TextEntry::make('approver.full_name')->label('Approved By')->placeholder('-'),
                    TextEntry::make('remarks')->placeholder('-')->columnSpanFull(),
                ]),
                Grid::make(['default' => 1, 'lg' => 2])->schema([
                    self::punchSection(
                        title: 'Punch In',
                        photoField: 'punch_in_photo',
                        photoLabel: 'Punch In Photo',
                        timeLabel: 'Date & time',
                        timeResolver: fn (Attendance $record): string => self::formatDateTime($record->punchInAt()),
                        locationLabel: 'Location',
                        locationResolver: fn (Attendance $record): string => self::locationLabel(
                            $record->punch_in_location,
                            $record->punch_in_latitude,
                            $record->punch_in_longitude,
                        ),
                        mapResolver: fn (Attendance $record): ?string => $record->punchInMapsUrl(),
                    ),
                    self::punchSection(
                        title: 'Punch Out',
                        photoField: 'punch_out_photo',
                        photoLabel: 'Punch Out Photo',
                        timeLabel: 'Date & time',
                        timeResolver: fn (Attendance $record): string => self::formatDateTime($record->punchOutAt()),
                        locationLabel: 'Location',
                        locationResolver: fn (Attendance $record): string => self::locationLabel(
                            $record->punch_out_location,
                            $record->punch_out_latitude,
                            $record->punch_out_longitude,
                        ),
                        mapResolver: fn (Attendance $record): ?string => $record->punchOutMapsUrl(),
                    ),
                ]),
            ]);
    }

    /**
     * @param  callable(Attendance): string  $timeResolver
     * @param  callable(Attendance): string  $locationResolver
     * @param  callable(Attendance): ?string  $mapResolver
     */
    private static function punchSection(
        string $title,
        string $photoField,
        string $photoLabel,
        string $timeLabel,
        callable $timeResolver,
        string $locationLabel,
        callable $locationResolver,
        callable $mapResolver,
    ): Section {
        return Section::make($title)->schema([
            ImageEntry::make($photoField)
                ->label($photoLabel)
                ->state(fn (Attendance $record): ?string => PublicStorage::url($record->{$photoField} ?? null))
                ->url(fn (?string $state): ?string => $state, shouldOpenInNewTab: true)
                ->imageHeight(240)
                ->checkFileExistence(false)
                ->extraImgAttributes([
                    'alt' => $photoLabel,
                    'class' => 'cursor-pointer',
                ])
                ->placeholder('No photo'),
            TextEntry::make($photoField.'_at')
                ->label($timeLabel)
                ->state(fn (Attendance $record): string => $timeResolver($record)),
            TextEntry::make($photoField.'_location')
                ->label($locationLabel)
                ->state(fn (Attendance $record): string => $locationResolver($record))
                ->url(fn (Attendance $record): ?string => $mapResolver($record))
                ->openUrlInNewTab()
                ->placeholder('-'),
            TextEntry::make($photoField.'_map')
                ->label('Map')
                ->state(fn (Attendance $record): string => filled($mapResolver($record)) ? 'Open in Google Maps' : '-')
                ->url(fn (Attendance $record): ?string => $mapResolver($record))
                ->openUrlInNewTab()
                ->visible(fn (Attendance $record): bool => filled($mapResolver($record))),
        ]);
    }

    private static function formatDateTime(mixed $at): string
    {
        if ($at === null) {
            return '-';
        }

        return $at->timezone(AttendanceCalendar::TIMEZONE)->format('d M Y, h:i A');
    }

    private static function locationLabel(mixed $location, mixed $latitude, mixed $longitude): string
    {
        if (filled($location)) {
            return (string) $location;
        }

        if ($latitude === null || $longitude === null || $latitude === '' || $longitude === '') {
            return '-';
        }

        return $latitude.', '.$longitude;
    }
}
