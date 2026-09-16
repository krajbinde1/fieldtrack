<?php

namespace App\Filament\Resources\Attendances\Schemas;

use App\Models\Attendance;
use App\Support\AttendanceCalendar;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class AttendanceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Attendance details')->columns(3)->schema([
                    TextEntry::make('employee.full_name')->label('Employee'),
                    TextEntry::make('attendance_date')->label('Attendance Date')->date('d M Y'),
                    TextEntry::make('attendance_status')
                        ->label('Attendance Status')
                        ->badge()
                        ->formatStateUsing(fn ($state): string => \App\Models\Attendance::ATTENDANCE_STATUS_LABELS[(string) $state] ?? (string) $state),
                    TextEntry::make('punch_in_time')
                        ->label('Punch In Time (IST)')
                        ->formatStateUsing(fn ($record): string => $record->punchInAt()?->timezone(AttendanceCalendar::TIMEZONE)->format('h:i A') ?? '-')
                        ->placeholder('-'),
                    TextEntry::make('punch_out_time')
                        ->label('Punch Out Time (IST)')
                        ->formatStateUsing(fn ($record): string => $record->punchOutAt()?->timezone(AttendanceCalendar::TIMEZONE)->format('h:i A') ?? '-')
                        ->placeholder('-'),
                    TextEntry::make('working_hours')
                        ->label('Working Hours')
                        ->formatStateUsing(fn ($record): string => app(\App\Services\Attendance\AttendanceStatusCalculator::class)->formatWorkingHoursLabel($record))
                        ->placeholder('-'),
                    TextEntry::make('approval_status')->label('Approval Status')->badge(),
                    TextEntry::make('approver.full_name')->label('Approved By')->placeholder('-'),
                    TextEntry::make('remarks')->placeholder('-')->columnSpanFull(),
                ]),
                Section::make('Locations')->columns(2)->schema([
                    TextEntry::make('punch_in_location')
                        ->label('Punch in location')
                        ->placeholder('-')
                        ->url(fn (Attendance $record): ?string => $record->punchInMapsUrl())
                        ->openUrlInNewTab(),
                    TextEntry::make('punch_out_location')
                        ->label('Punch out location')
                        ->placeholder('-')
                        ->url(fn (Attendance $record): ?string => $record->punchOutMapsUrl())
                        ->openUrlInNewTab(),
                    TextEntry::make('punch_in_latitude')
                        ->numeric()
                        ->placeholder('-')
                        ->url(fn (Attendance $record): ?string => $record->punchInMapsUrl())
                        ->openUrlInNewTab(),
                    TextEntry::make('punch_in_longitude')
                        ->numeric()
                        ->placeholder('-')
                        ->url(fn (Attendance $record): ?string => $record->punchInMapsUrl())
                        ->openUrlInNewTab(),
                    TextEntry::make('punch_out_latitude')
                        ->numeric()
                        ->placeholder('-')
                        ->url(fn (Attendance $record): ?string => $record->punchOutMapsUrl())
                        ->openUrlInNewTab(),
                    TextEntry::make('punch_out_longitude')
                        ->numeric()
                        ->placeholder('-')
                        ->url(fn (Attendance $record): ?string => $record->punchOutMapsUrl())
                        ->openUrlInNewTab(),
                ]),
                Section::make('Punch photos')->columns(2)->schema([
                    ImageEntry::make('punch_in_photo')
                        ->label('Punch In Photo')
                        ->disk('public')
                        ->visibility('public')
                        ->imageHeight(240)
                        ->url(fn (?string $state): ?string => filled($state) ? Storage::disk('public')->url($state) : null)
                        ->openUrlInNewTab()
                        ->placeholder('No photo'),
                    ImageEntry::make('punch_out_photo')
                        ->label('Punch Out Photo')
                        ->disk('public')
                        ->visibility('public')
                        ->imageHeight(240)
                        ->url(fn (?string $state): ?string => filled($state) ? Storage::disk('public')->url($state) : null)
                        ->openUrlInNewTab()
                        ->placeholder('No photo'),
                ]),
            ]);
    }
}
