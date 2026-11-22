<x-filament-widgets::widget>
    <section class="ft-coord-modules">
        <div class="ft-coord-modules-head">
            <div>
                <p class="ft-cm-kicker">Center modules</p>
                <h2 class="ft-coord-modules-title">{{ $this->centerName() }}</h2>
                <p class="ft-cm-meta">These modules show records for the selected center.</p>
            </div>
            @if ($this->canSwitchCenters())
                <a class="ft-cm-chip" href="{{ $this->switchUrl() }}">Switch center</a>
            @endif
        </div>

        <div class="ft-coord-module-grid">
            @foreach ($this->modules() as $module)
                <a class="ft-coord-module-link" href="{{ $module['url'] }}">
                    {{ $module['label'] }}
                    <span>Open</span>
                </a>
            @endforeach
        </div>
    </section>
</x-filament-widgets::widget>
