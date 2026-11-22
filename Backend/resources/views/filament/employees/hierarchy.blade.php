<div class="ft-team-tree">
    @forelse ($tree as $center)
        <section class="ft-team-center" x-data="{ open: true }">
            <button type="button" class="ft-team-center-toggle" @click="open = !open">
                <span class="ft-team-chevron" :class="{ 'is-open': open }" aria-hidden="true">▸</span>
                <span>{{ $center['name'] }}</span>
            </button>
            <div class="ft-team-people" x-show="open" x-cloak>
                @foreach (array_merge($center['managers'], $center['employees']) as $person)
                    <div class="ft-team-row @if ($person['kind'] === 'employee') is-employee @endif">
                        <div class="ft-team-person">
                            <span class="ft-team-name">{{ $person['name'] }}</span>
                            <span class="ft-team-role">{{ $person['meta'] }}</span>
                            @unless ($person['active'])
                                <span class="ft-team-badge">Inactive</span>
                            @endunless
                        </div>
                        @if ($person['editUrl'] || $person['canReset'] || $person['canDelete'] || $person['canToggle'])
                            <div class="ft-team-actions" aria-label="Actions">
                                @include('filament.employees.hierarchy-actions', ['person' => $person, 'menu' => false])
                            </div>
                            <details class="ft-team-more">
                                <summary aria-label="Actions">⋯</summary>
                                <div class="ft-team-menu">
                                    @include('filament.employees.hierarchy-actions', ['person' => $person, 'menu' => true])
                                </div>
                            </details>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <p class="ft-team-empty">No centers are assigned.</p>
    @endforelse
</div>

<style>
    .ft-team-tree { display: flex; flex-direction: column; gap: 0.55rem; }
    .ft-team-center { border: 1px solid #e6e2f5; border-radius: 12px; background: #fff; }
    .ft-team-center-toggle { display: flex; align-items: center; gap: 0.4rem; width: 100%; padding: 0.55rem 0.75rem; border: 0; background: #f6f3fc; color: #312e81; font-size: 0.84rem; font-weight: 800; text-align: left; cursor: pointer; }
    .ft-team-chevron { display: inline-block; transition: transform 0.15s ease; }
    .ft-team-chevron.is-open { transform: rotate(90deg); }
    .ft-team-people { display: flex; flex-direction: column; }
    .ft-team-row { display: flex; align-items: center; justify-content: space-between; gap: 0.6rem; padding: 0.4rem 0.75rem 0.4rem 1.7rem; border-top: 1px solid #f1f5f9; }
    .ft-team-row.is-employee { padding-left: 2.35rem; }
    .ft-team-person { display: flex; align-items: center; gap: 0.4rem; min-width: 0; }
    .ft-team-name { font-size: 0.8rem; font-weight: 700; color: #0f172a; }
    .ft-team-role, .ft-team-badge { font-size: 0.68rem; color: #64748b; }
    .ft-team-badge { border-radius: 999px; background: #f1f5f9; padding: 0.05rem 0.4rem; }
    .ft-team-actions, .ft-team-menu { display: flex; align-items: center; gap: 0.15rem; }
    .ft-team-actions a, .ft-team-actions button, .ft-team-menu a, .ft-team-menu button {
        display: inline-flex; align-items: center; justify-content: center; width: 1.7rem; height: 1.7rem;
        border: 0; border-radius: 0.4rem; background: transparent; color: #5b21b6; cursor: pointer; text-decoration: none;
    }
    .ft-team-actions a:hover, .ft-team-actions button:hover, .ft-team-menu a:hover, .ft-team-menu button:hover { background: #f3eaff; }
    .ft-team-actions .is-danger, .ft-team-menu .is-danger { color: #dc2626; }
    .ft-team-actions svg, .ft-team-menu svg { width: 0.95rem; height: 0.95rem; }
    .ft-team-more { display: none; position: relative; }
    .ft-team-more summary { list-style: none; width: 1.7rem; height: 1.7rem; border-radius: 0.4rem; text-align: center; line-height: 1.7rem; cursor: pointer; color: #5b21b6; font-weight: 800; }
    .ft-team-more summary::-webkit-details-marker { display: none; }
    .ft-team-menu { position: absolute; right: 0; z-index: 20; display: flex; flex-direction: column; min-width: 9.5rem; padding: 0.25rem; border: 1px solid #e6e2f5; border-radius: 0.6rem; background: #fff; box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08); }
    .ft-team-menu a, .ft-team-menu button { width: 100%; justify-content: flex-start; gap: 0.35rem; height: auto; padding: 0.3rem 0.4rem; font-size: 0.75rem; font-weight: 700; }
    .ft-team-empty { margin: 0; font-size: 0.8rem; color: #64748b; }
    @media (max-width: 767px) {
        .ft-team-actions { display: none; }
        .ft-team-more { display: block; }
    }
</style>
