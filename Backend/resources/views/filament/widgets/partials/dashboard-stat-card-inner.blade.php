<span class="ft-dash-card-icon" aria-hidden="true">
    @include('filament.widgets.partials.dashboard-stat-icon', ['icon' => $card['icon']])
</span>
<span class="ft-dash-card-copy">
    <span class="ft-dash-card-title">{{ $card['label'] }}</span>
    <span class="ft-dash-card-value">{{ $card['value'] }}</span>
    @if (filled($card['hint'] ?? null))
        <span class="ft-dash-card-hint">{{ $card['hint'] }}</span>
    @endif
</span>
