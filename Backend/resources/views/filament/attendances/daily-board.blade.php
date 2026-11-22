<div class="ft-attendance-board">
    <div class="ft-attendance-head">
        <div>
            <h2 class="ft-attendance-title">Daily Attendance Summary</h2>
            <p class="ft-attendance-date">{{ $dateLabel }}</p>
        </div>
        <div class="ft-attendance-toolbar">
            <button type="button" wire:click="previousDay">Previous Day</button>
            <input type="date" wire:model.live="attendanceDate" aria-label="Attendance date" />
            <button type="button" wire:click="nextDay">Next Day</button>
            <button type="button" wire:click="goToToday" @disabled($isToday)>Today</button>
        </div>
    </div>

    @if ($showScheme || $showCenter || filled($lockedCenter))
        <div class="ft-attendance-filters">
            @if ($showScheme)
                <label>
                    <span>Scheme</span>
                    <select wire:model.live="schemeId" aria-label="Scheme">
                        <option value="">All</option>
                        @foreach ($schemes as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            @if ($showCenter)
                <label>
                    <span>Center</span>
                    <select wire:model.live="centerId" aria-label="Center">
                        <option value="">All</option>
                        @foreach ($centers as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </label>
            @elseif (filled($lockedCenter))
                <p class="ft-attendance-locked">Center: {{ $lockedCenter }}</p>
            @endif
        </div>
    @endif

    <div class="ft-attendance-cards">
        @foreach ($cards as $card)
            <button
                type="button"
                wire:click="toggleCard('{{ $card['key'] }}')"
                class="ft-dash-card ft-dash-card--{{ $card['tone'] }} is-link {{ $card['active'] ? 'is-active' : '' }}"
                aria-pressed="{{ $card['active'] ? 'true' : 'false' }}"
            >
                @include('filament.widgets.partials.dashboard-stat-card-inner', ['card' => $card])
            </button>
        @endforeach
    </div>
</div>

<style>
    .ft-attendance-board { display: flex; flex-direction: column; gap: 0.85rem; margin-bottom: 0.4rem; }
    .ft-attendance-head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 0.75rem; align-items: flex-end; }
    .ft-attendance-title { margin: 0; font-size: 1.05rem; font-weight: 700; color: #1e1b4b; }
    .ft-attendance-date { margin: 0.15rem 0 0; color: #64748b; font-size: 0.88rem; }
    .ft-attendance-toolbar, .ft-attendance-filters { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
    .ft-attendance-toolbar button, .ft-attendance-filters select, .ft-attendance-toolbar input[type="date"] {
        border: 1px solid #ddd6fe;
        background: #fff;
        color: #312e81;
        border-radius: 10px;
        padding: 0.4rem 0.7rem;
        font: inherit;
        font-size: 0.84rem;
    }
    .ft-attendance-toolbar button { cursor: pointer; }
    .ft-attendance-toolbar button:disabled { opacity: 0.55; cursor: default; }
    .ft-attendance-filters label { display: flex; flex-direction: column; gap: 0.2rem; font-size: 0.75rem; color: #64748b; }
    .ft-attendance-locked { margin: 0; color: #4338ca; font-size: 0.88rem; font-weight: 600; }
    .ft-attendance-cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.7rem; }
    .ft-attendance-board button.ft-dash-card { width: 100%; text-align: left; font: inherit; cursor: pointer; appearance: none; }
    .ft-attendance-board button.ft-dash-card.is-active { box-shadow: 0 0 0 2px #5b3aa8; }
    @media (max-width: 960px) { .ft-attendance-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 640px) { .ft-attendance-cards { grid-template-columns: 1fr; } }
</style>
