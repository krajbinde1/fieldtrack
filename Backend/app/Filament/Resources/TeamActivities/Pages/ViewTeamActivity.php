<?php

namespace App\Filament\Resources\TeamActivities\Pages;

use App\Enums\FieldActivityType;
use App\Filament\Resources\TeamActivities\TeamActivityResource;
use App\Models\Employee;
use App\Models\FieldActivity;
use App\Services\AdminCenterContext;
use App\Services\OrganizationAccessService;
use App\Support\AttendanceCalendar;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ViewTeamActivity extends ViewRecord
{
    protected static string $resource = TeamActivityResource::class;

    public string $period = 'today';

    public ?string $on = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $employee = $this->getRecord();
        $user = auth()->user();
        if ($employee instanceof Employee && $user !== null) {
            app(AdminCenterContext::class)->abortUnlessEmployeeInAdminCenter($employee);
            app(OrganizationAccessService::class)->assertCanViewEmployee($user, $employee);
        }
    }

    public function getTitle(): string|Htmlable
    {
        $record = $this->getRecord();

        return $record instanceof Employee ? (string) $record->full_name : 'Team Activity';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Activities on '.$this->selectedDate()->format('d M Y');
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.team-activities.employee')
                ->columnSpanFull()
                ->viewData(fn (): array => [
                    'period' => $this->period,
                    'on' => $this->period === 'custom' ? $this->selectedDate()->toDateString() : $this->on,
                    'activities' => $this->activityRows(),
                ]),
        ]);
    }

    public function showPeriod(string $period): void
    {
        $this->period = in_array($period, ['today', 'yesterday', 'custom'], true) ? $period : 'today';

        if ($this->period === 'custom' && ! $this->validOn()) {
            $this->on = AttendanceCalendar::today()->toDateString();
        }

        if ($this->period !== 'custom') {
            $this->on = null;
        }
    }

    public function updatedOn(): void
    {
        $this->period = 'custom';

        if (! $this->validOn()) {
            $this->on = AttendanceCalendar::today()->toDateString();
        }
    }

    /**
     * @return list<array{when: string, type: string, description: string, photo: ?string, maps: ?string, location: ?string}>
     */
    public function activityRows(): array
    {
        return $this->activities()
            ->map(function (FieldActivity $activity): array {
                $type = $activity->activity_type instanceof FieldActivityType
                    ? $activity->activity_type
                    : FieldActivityType::tryFromMixed((string) $activity->activity_type);
                $at = $activity->activity_at?->timezone(AttendanceCalendar::TIMEZONE);
                $name = trim((string) $activity->activity_name);
                $remarks = trim((string) $activity->remarks);
                $parts = [];

                if ($name !== '' && strcasecmp($name, $type->label()) !== 0) {
                    $parts[] = $name;
                }
                if ($remarks !== '') {
                    $parts[] = $remarks;
                }

                return [
                    'when' => $at?->format('d M Y, h:i A') ?? '—',
                    'type' => $type->label(),
                    'description' => $parts === [] ? '—' : implode(' — ', $parts),
                    'photo' => filled($activity->photo_path) ? $activity->photoUrl() : null,
                    'maps' => $activity->mapsUrl(),
                    'location' => filled($activity->location) ? trim((string) $activity->location) : null,
                ];
            })
            ->all();
    }

    /**
     * @return Collection<int, FieldActivity>
     */
    private function activities()
    {
        $employee = $this->getRecord();
        $user = auth()->user();
        $query = app(OrganizationAccessService::class)
            ->fieldActivityQuery($user)
            ->where('employee_id', $employee instanceof Employee ? $employee->id : 0);

        $centerId = app(AdminCenterContext::class)->idFor($user);
        if ($centerId !== null) {
            $query->where('center_id', $centerId);
        }

        return $query
            ->whereDate('activity_at', $this->selectedDate()->toDateString())
            ->orderBy('activity_at')
            ->orderBy('id')
            ->get();
    }

    private function selectedDate(): Carbon
    {
        if ($this->period === 'yesterday') {
            return AttendanceCalendar::today()->subDay();
        }

        if ($this->period === 'custom' && $this->validOn()) {
            return Carbon::parse($this->on, AttendanceCalendar::TIMEZONE)->startOfDay();
        }

        return AttendanceCalendar::today();
    }

    private function validOn(): bool
    {
        if (! is_string($this->on) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->on) !== 1) {
            return false;
        }

        $parsed = Carbon::createFromFormat('Y-m-d', $this->on, AttendanceCalendar::TIMEZONE);

        return $parsed instanceof Carbon && $parsed->format('Y-m-d') === $this->on;
    }
}
