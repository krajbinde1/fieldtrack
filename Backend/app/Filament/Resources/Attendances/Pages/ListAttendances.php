<?php

namespace App\Filament\Resources\Attendances\Pages;

use App\Filament\Resources\Attendances\AttendanceResource;
use App\Filament\Resources\Attendances\Widgets\MonthlyAttendanceSummary;
use App\Filament\Support\TodayDateFilter;
use App\Models\User;
use App\Services\Attendance\DailyAttendanceBoard;
use App\Support\AttendanceCalendar;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

class ListAttendances extends ListRecords
{
    protected static string $resource = AttendanceResource::class;

    #[Url(as: 'on')]
    public ?string $attendanceDate = null;

    #[Url(as: 'card')]
    public ?string $attendanceCard = null;

    #[Url(as: 'scheme')]
    public ?string $schemeId = null;

    #[Url(as: 'center')]
    public ?string $centerId = null;

    /** @var array{key: string, value: array{counts: array<string, int>, visible_ids: list<int>, closed: bool}}|null */
    private ?array $boardCache = null;

    public function mount(): void
    {
        parent::mount();
        $this->normalizeBoardState();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Review daily punch-in, punch-out, and attendance status for your authorized employees.';
    }

    public function previousDay(): void
    {
        $this->attendanceDate = $this->selectedDate()->copy()->subDay()->toDateString();
        $this->refreshBoard();
    }

    public function nextDay(): void
    {
        $this->attendanceDate = $this->selectedDate()->copy()->addDay()->toDateString();
        $this->refreshBoard();
    }

    public function goToToday(): void
    {
        $this->attendanceDate = AttendanceCalendar::today()->toDateString();
        $this->refreshBoard();
    }

    public function toggleCard(string $card): void
    {
        if (! in_array($card, DailyAttendanceBoard::CARD_KEYS, true)) {
            return;
        }

        $this->attendanceCard = $this->attendanceCard === $card ? null : $card;
        $this->refreshBoard();
    }

    public function updatedAttendanceDate(): void
    {
        $this->attendanceDate = TodayDateFilter::normalizeDate($this->attendanceDate)
            ?? AttendanceCalendar::today()->toDateString();
        $this->refreshBoard();
    }

    public function updatedSchemeId(): void
    {
        $this->normalizeBoardState();
    }

    public function updatedCenterId(): void
    {
        $this->normalizeBoardState();
    }

    /**
     * @return array<string, int>
     */
    public function summaryCounts(): array
    {
        return $this->board()['counts'];
    }

    public function showsSchemeFilter(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdminOrDirector() && $this->boardService()->schemeOptions($user) !== [];
    }

    public function showsCenterFilter(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $this->boardService()->showsCenterFilter($user);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.attendances.daily-board')
                ->columnSpanFull()
                ->viewData(fn (): array => $this->boardViewData()),
            $this->getTabsContentComponent(),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
            EmbeddedTable::make(),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Daily roster is the main table. Monthly summary stays below it.
     */
    protected function getFooterWidgets(): array
    {
        return [
            MonthlyAttendanceSummary::class,
        ];
    }

    protected function getTableQuery(): Builder
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return app(DailyAttendanceBoard::class)->tableQuery($this->selectedDate()->toDateString(), []);
        }

        return $this->boardService()->tableQuery(
            $this->selectedDate()->toDateString(),
            $this->board()['visible_ids'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function boardViewData(): array
    {
        $user = auth()->user();
        $board = $this->board();
        $service = $this->boardService();
        $schemeId = $this->selectedSchemeId();
        $centers = $user instanceof User ? $service->centerOptions($user, $schemeId) : [];
        $showCenter = $user instanceof User && $service->showsCenterFilter($user);
        $date = $this->selectedDate();
        $cards = [];

        foreach ($service->cardCatalog($board['closed']) as $key => $meta) {
            $cards[] = [
                'key' => $key,
                'label' => $meta['label'],
                'value' => (string) ($board['counts'][$key] ?? 0),
                'hint' => $meta['hint'],
                'icon' => $meta['icon'],
                'tone' => $meta['tone'],
                'active' => $this->attendanceCard === $key,
            ];
        }

        return [
            'dateLabel' => $date->format('l, d M Y'),
            'isToday' => $date->isSameDay(AttendanceCalendar::today()),
            'cards' => $cards,
            'showScheme' => $this->showsSchemeFilter(),
            'showCenter' => $showCenter,
            'schemes' => $user instanceof User ? $service->schemeOptions($user) : [],
            'centers' => $centers,
            'lockedCenter' => ! $showCenter && count($centers) === 1 ? reset($centers) : null,
        ];
    }

    /**
     * @return array{counts: array<string, int>, visible_ids: list<int>, closed: bool}
     */
    private function board(): array
    {
        $key = implode('|', [
            $this->selectedDate()->toDateString(),
            (string) $this->selectedSchemeId(),
            (string) $this->selectedCenterId(),
            (string) $this->activeCard(),
            (string) auth()->id(),
        ]);

        if (($this->boardCache['key'] ?? null) === $key) {
            return $this->boardCache['value'];
        }

        $user = auth()->user();
        $value = $user instanceof User
            ? $this->boardService()->build(
                $user,
                $this->selectedDate(),
                $this->selectedSchemeId(),
                $this->selectedCenterId(),
                $this->activeCard(),
            )
            : [
                'counts' => array_fill_keys(DailyAttendanceBoard::CARD_KEYS, 0),
                'visible_ids' => [],
                'closed' => false,
            ];

        $this->boardCache = ['key' => $key, 'value' => $value];

        return $value;
    }

    private function boardService(): DailyAttendanceBoard
    {
        return app(DailyAttendanceBoard::class);
    }

    private function selectedDate(): Carbon
    {
        $normalized = TodayDateFilter::normalizeDate($this->attendanceDate)
            ?? AttendanceCalendar::today()->toDateString();

        return Carbon::parse($normalized, AttendanceCalendar::TIMEZONE)->startOfDay();
    }

    private function selectedSchemeId(): ?int
    {
        $user = auth()->user();
        if (! $user instanceof User || ! $user->isAdminOrDirector() || blank($this->schemeId)) {
            return null;
        }

        $schemeId = (int) $this->schemeId;

        return array_key_exists($schemeId, $this->boardService()->schemeOptions($user)) ? $schemeId : null;
    }

    private function selectedCenterId(): ?int
    {
        $user = auth()->user();
        if (! $user instanceof User || blank($this->centerId)) {
            return null;
        }

        $centerId = (int) $this->centerId;
        $options = $this->boardService()->centerOptions($user, $this->selectedSchemeId());

        return array_key_exists($centerId, $options) ? $centerId : null;
    }

    private function activeCard(): ?string
    {
        return in_array($this->attendanceCard, DailyAttendanceBoard::CARD_KEYS, true)
            ? $this->attendanceCard
            : null;
    }

    private function normalizeBoardState(): void
    {
        $this->attendanceDate = TodayDateFilter::normalizeDate($this->attendanceDate)
            ?? AttendanceCalendar::today()->toDateString();

        $user = auth()->user();
        if ($user instanceof User) {
            $schemeId = $this->selectedSchemeId();
            $this->schemeId = $schemeId === null ? null : (string) $schemeId;

            $centerId = $this->selectedCenterId();
            $this->centerId = $centerId === null ? null : (string) $centerId;
        }

        if (! in_array($this->attendanceCard, DailyAttendanceBoard::CARD_KEYS, true)) {
            $this->attendanceCard = null;
        }

        $this->refreshBoard();
    }

    private function refreshBoard(): void
    {
        $this->boardCache = null;
        $this->resetTable();
    }
}
