@php
    $user = auth()->user();
    $showMainDashboard = $user instanceof \App\Models\User && $user->isAdmin();

    if ($showMainDashboard) {
        $dashboardUrl = \App\Filament\Pages\Dashboard::getUrl();
        $isMainDashboard = \Filament\Support\original_request()->routeIs(
            \App\Filament\Pages\Dashboard::getRouteName(),
        );
        $dashboardLabel = \App\Filament\Pages\Dashboard::getNavigationLabel();
    }
@endphp

@if ($showMainDashboard)
    <a
        href="{{ $dashboardUrl }}"
        class="ft-main-dashboard-link{{ $isMainDashboard ? ' is-active' : '' }}"
        @if ($isMainDashboard) aria-current="page" @endif
    >
        <span class="ft-main-dashboard-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
                <rect x="3.5" y="3.5" width="7" height="7" rx="1.6" stroke="currentColor" stroke-width="1.7"/>
                <rect x="13.5" y="3.5" width="7" height="7" rx="1.6" stroke="currentColor" stroke-width="1.7"/>
                <rect x="3.5" y="13.5" width="7" height="7" rx="1.6" stroke="currentColor" stroke-width="1.7"/>
                <rect x="13.5" y="13.5" width="7" height="7" rx="1.6" stroke="currentColor" stroke-width="1.7"/>
            </svg>
        </span>
        <span class="ft-main-dashboard-label">{{ $dashboardLabel }}</span>
    </a>
@endif
