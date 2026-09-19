<div class="fi-wi-widget ft-dash-stats">
    @foreach ($this->getCards() as $card)
        @if (filled($card['url'] ?? null))
            <a href="{{ $card['url'] }}" class="ft-dash-card ft-dash-card--{{ $card['tone'] }} is-link">
                @include('filament.widgets.partials.dashboard-stat-card-inner', ['card' => $card])
            </a>
        @else
            <div class="ft-dash-card ft-dash-card--{{ $card['tone'] }}">
                @include('filament.widgets.partials.dashboard-stat-card-inner', ['card' => $card])
            </div>
        @endif
    @endforeach
</div>
