<div class="ft-team-activity">
    <div class="ft-team-activity-filters">
        <button type="button" wire:click="showPeriod('today')" @class(['is-active' => $period === 'today'])>Today</button>
        <button type="button" wire:click="showPeriod('yesterday')" @class(['is-active' => $period === 'yesterday'])>Yesterday</button>
        <button type="button" wire:click="showPeriod('custom')" @class(['is-active' => $period === 'custom'])>Custom Date</button>
        <input type="date" wire:model.live="on" aria-label="Custom date" />
    </div>

    @if ($activities === [])
        <p class="ft-team-activity-empty">No Activity Found</p>
    @else
        <div class="ft-team-activity-list">
            @foreach ($activities as $activity)
                <article class="ft-team-activity-card">
                    <p class="ft-team-activity-when">{{ $activity['when'] }}</p>
                    <p class="ft-team-activity-type">{{ $activity['type'] }}</p>
                    <p class="ft-team-activity-notes">{{ $activity['description'] }}</p>
                    @if (filled($activity['photo']))
                        <img src="{{ $activity['photo'] }}" alt="Activity photo" />
                    @endif
                    @if (filled($activity['maps']))
                        <a href="{{ $activity['maps'] }}" target="_blank" rel="noopener noreferrer">
                            {{ $activity['location'] ?: 'Open map' }}
                        </a>
                    @elseif (filled($activity['location']))
                        <p>{{ $activity['location'] }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</div>

<style>
    .ft-team-activity { display: flex; flex-direction: column; gap: 0.75rem; }
    .ft-team-activity-filters { display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: center; }
    .ft-team-activity-filters button,
    .ft-team-activity-filters input {
        border: 1px solid rgb(209 213 219);
        background: white;
        border-radius: 0.4rem;
        padding: 0.3rem 0.65rem;
        font-size: 0.85rem;
    }
    .ft-team-activity-filters button.is-active { background: rgb(79 70 229); color: white; border-color: rgb(79 70 229); }
    .ft-team-activity-empty { margin: 1.5rem 0; text-align: center; color: rgb(107 114 128); }
    .ft-team-activity-list { display: flex; flex-direction: column; gap: 0.6rem; }
    .ft-team-activity-card {
        border: 1px solid rgb(229 231 235);
        border-radius: 0.6rem;
        padding: 0.7rem 0.8rem;
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
    }
    .ft-team-activity-when { font-size: 0.8rem; color: rgb(107 114 128); }
    .ft-team-activity-type { font-weight: 600; }
    .ft-team-activity-notes { white-space: pre-line; }
    .ft-team-activity-card img { width: 140px; max-width: 100%; border-radius: 0.4rem; margin-top: 0.35rem; }
    .ft-team-activity-card a { color: rgb(79 70 229); font-size: 0.85rem; }
</style>
