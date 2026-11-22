@php
    $routeData = $this->getRouteMapData();
    $hasEnoughPoints = ($routeData['summary']['valid_point_count'] ?? 0) >= 2;
    $mapElementId = 'employee-route-map-'.$this->getRecord()->getKey();
    $panelElementId = 'employee-route-panel-'.$this->getRecord()->getKey();
    $diagnostics = $routeData['diagnostics'] ?? [];
    $sparseWarning = $diagnostics['sparse_warning'] ?? null;
    $journeyEvents = $routeData['journey_events'] ?? [];
    $employeeName = $routeData['employee']['full_name'] ?? 'Employee';
    $employeeCode = $routeData['employee']['employee_code'] ?? null;
    $attendanceDate = $routeData['attendance_date'] ?? '-';
    $attendanceDateIso = $routeData['attendance_date_iso'] ?? '';
    $summary = $routeData['summary'] ?? [];
    $navigation = $routeData['navigation'] ?? [];
    $stopCount = (int) ($summary['stop_count'] ?? 0);
    $distanceKm = number_format((float) ($summary['total_distance_km'] ?? 0), 1);
    $travelTimeLabel = $summary['travel_time_label'] ?? '0m';
    $punchInTime = $summary['punch_in_time'] ?? ($routeData['punch_in']['time'] ?? '—');
    $punchOutTime = $summary['punch_out_time'] ?? ($routeData['punch_out']['time'] ?? '—');
@endphp

@push('styles')
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin=""
    />
    <style>
        /* Full-page shell: hide residual Filament chrome when sidebar/topbar are disabled. */
        body.er-fullscreen-route .fi-topbar,
        body.er-fullscreen-route .fi-sidebar,
        body.er-fullscreen-route .fi-sidebar-close-overlay {
            display: none !important;
        }

        body.er-fullscreen-route .fi-main-ctn,
        body.er-fullscreen-route .fi-main,
        body.er-fullscreen-route .fi-page,
        body.er-fullscreen-route .fi-page-content {
            max-width: 100% !important;
            width: 100% !important;
        }

        body.er-fullscreen-route .fi-main {
            padding: 0 !important;
        }

        body.er-fullscreen-route .fi-page-header,
        body.er-fullscreen-route .fi-header,
        body.er-fullscreen-route .fi-breadcrumbs {
            display: none !important;
        }

        body.er-fullscreen-route .fi-page-content {
            padding: 0.5rem 0.65rem 0.65rem !important;
        }

        .er-page {
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
            width: 100%;
            max-width: 100%;
            height: calc(100vh - 1rem);
            min-height: calc(100vh - 1rem);
            overflow: hidden;
        }

        .er-header {
            flex-shrink: 0;
            z-index: 20;
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
            padding: 0.7rem 0.85rem;
            border-radius: 0.75rem;
            border: 1px solid rgb(229 231 235);
            background: rgb(255 255 255 / 0.98);
        }

        .dark .er-header {
            border-color: rgb(55 65 81);
            background: rgb(17 24 39 / 0.98);
        }

        .er-header-top {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.65rem;
        }

        .er-employee {
            min-width: 0;
        }

        .er-employee-name {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 700;
            color: rgb(17 24 39);
            line-height: 1.2;
        }

        .dark .er-employee-name {
            color: white;
        }

        .er-employee-meta {
            margin-top: 0.1rem;
            font-size: 0.8125rem;
            color: rgb(107 114 128);
        }

        .dark .er-employee-meta {
            color: rgb(156 163 175);
        }

        .er-back-link {
            margin-left: 0.5rem;
            font-size: 0.75rem;
            color: rgb(107 114 128);
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .er-back-link:hover {
            color: rgb(37 99 235);
        }

        .dark .er-back-link {
            color: rgb(156 163 175);
        }

        .er-date-nav {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            flex-wrap: wrap;
        }

        .er-nav-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.15rem;
            height: 2.15rem;
            border-radius: 0.6rem;
            border: 1px solid rgb(209 213 219);
            background: white;
            color: rgb(55 65 81);
            transition: background 0.15s ease, border-color 0.15s ease;
        }

        .er-nav-btn:hover:not([disabled]) {
            background: rgb(249 250 251);
            border-color: rgb(156 163 175);
        }

        .er-nav-btn[disabled] {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .dark .er-nav-btn {
            border-color: rgb(75 85 99);
            background: rgb(31 41 55);
            color: rgb(229 231 235);
        }

        .er-date-label {
            min-width: 7.25rem;
            text-align: center;
            font-size: 0.9375rem;
            font-weight: 600;
            color: rgb(17 24 39);
        }

        .dark .er-date-label {
            color: white;
        }

        .er-date-input {
            height: 2.15rem;
            border-radius: 0.6rem;
            border: 1px solid rgb(209 213 219);
            background: white;
            padding: 0 0.65rem;
            font-size: 0.8125rem;
            color: rgb(55 65 81);
        }

        .dark .er-date-input {
            border-color: rgb(75 85 99);
            background: rgb(31 41 55);
            color: rgb(229 231 235);
            color-scheme: dark;
        }

        .er-toggle {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
            font-weight: 500;
            color: rgb(55 65 81);
            cursor: pointer;
            user-select: none;
        }

        .dark .er-toggle {
            color: rgb(209 213 219);
        }

        .er-toggle input {
            width: 1rem;
            height: 1rem;
            accent-color: rgb(37 99 235);
        }

        .er-summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.45rem;
        }

        @media (min-width: 768px) {
            .er-summary {
                grid-template-columns: repeat(5, minmax(0, 1fr));
            }
        }

        .er-summary-card {
            min-width: 0;
            border-radius: 0.65rem;
            border: 1px solid rgb(243 244 246);
            background: rgb(249 250 251);
            padding: 0.45rem 0.65rem;
        }

        .dark .er-summary-card {
            border-color: rgb(55 65 81);
            background: rgb(31 41 55);
        }

        .er-summary-label {
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            color: rgb(107 114 128);
        }

        .dark .er-summary-label {
            color: rgb(156 163 175);
        }

        .er-summary-value {
            margin-top: 0.1rem;
            font-size: 0.9rem;
            font-weight: 700;
            color: rgb(17 24 39);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dark .er-summary-value {
            color: white;
        }

        .er-body {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.55rem;
            flex: 1 1 auto;
            min-height: 0;
            overflow: hidden;
        }

        @media (min-width: 1024px) {
            .er-body {
                grid-template-columns: minmax(260px, 30%) minmax(0, 70%);
                align-items: stretch;
            }
        }

        .er-panel {
            display: flex;
            flex-direction: column;
            min-height: 0;
            height: 100%;
            border-radius: 0.75rem;
            border: 1px solid rgb(229 231 235);
            background: white;
            overflow: hidden;
        }

        .dark .er-panel {
            border-color: rgb(55 65 81);
            background: rgb(17 24 39);
        }

        .er-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            padding: 0.65rem 0.8rem;
            border-bottom: 1px solid rgb(243 244 246);
            flex-shrink: 0;
        }

        .dark .er-panel-head {
            border-bottom-color: rgb(55 65 81);
        }

        .er-panel-title {
            margin: 0;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: rgb(107 114 128);
        }

        .dark .er-panel-title {
            color: rgb(156 163 175);
        }

        .er-panel-count {
            font-size: 0.75rem;
            color: rgb(107 114 128);
        }

        .er-panel-list {
            flex: 1;
            overflow-y: auto;
            min-height: 0;
            max-height: none;
            padding: 0.45rem;
        }

        .er-event {
            display: flex;
            gap: 0.7rem;
            width: 100%;
            text-align: left;
            border: 1px solid transparent;
            border-radius: 0.75rem;
            background: transparent;
            padding: 0.7rem 0.65rem;
            cursor: default;
            transition: background 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .er-event[data-clickable="1"] {
            cursor: pointer;
        }

        .er-event[data-clickable="1"]:hover {
            background: rgb(249 250 251);
            border-color: rgb(229 231 235);
        }

        .dark .er-event[data-clickable="1"]:hover {
            background: rgb(31 41 55);
            border-color: rgb(55 65 81);
        }

        .er-event.is-active {
            background: rgb(239 246 255);
            border-color: rgb(147 197 253);
            box-shadow: 0 0 0 1px rgb(191 219 254);
        }

        .dark .er-event.is-active {
            background: rgb(30 58 138 / 0.35);
            border-color: rgb(59 130 246);
            box-shadow: none;
        }

        .er-event-travel {
            margin: 0.2rem 0.15rem;
            border-radius: 0.65rem;
            border: 1px dashed rgb(209 213 219);
            background: rgb(249 250 251);
            padding: 0.55rem 0.7rem;
            color: rgb(75 85 99);
        }

        .dark .er-event-travel {
            border-color: rgb(75 85 99);
            background: rgb(31 41 55);
            color: rgb(209 213 219);
        }

        .er-event-badge {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.85rem;
            height: 1.85rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            color: white;
            background: #ea580c;
            box-shadow: 0 0 0 2px white, 0 1px 3px rgb(0 0 0 / 0.2);
        }

        .er-event-badge.start {
            background: #16a34a;
            font-size: 0.55rem;
            letter-spacing: 0.02em;
        }

        .er-event-badge.end {
            background: #dc2626;
            font-size: 0.55rem;
            letter-spacing: 0.02em;
        }

        .er-event-body {
            min-width: 0;
            flex: 1;
        }

        .er-event-title {
            font-size: 0.875rem;
            font-weight: 650;
            color: rgb(17 24 39);
            line-height: 1.3;
        }

        .dark .er-event-title {
            color: white;
        }

        .er-event-location {
            margin-top: 0.1rem;
            font-size: 0.75rem;
            color: rgb(107 114 128);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dark .er-event-location {
            color: rgb(156 163 175);
        }

        .er-event-time {
            margin-top: 0.25rem;
            font-size: 0.8125rem;
            font-weight: 500;
            color: rgb(55 65 81);
        }

        .dark .er-event-time {
            color: rgb(209 213 219);
        }

        .er-event-meta {
            margin-top: 0.15rem;
            font-size: 0.75rem;
            color: rgb(107 114 128);
        }

        .er-travel-title {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.8125rem;
            font-weight: 650;
        }

        .er-map-wrap {
            display: flex;
            flex-direction: column;
            min-height: 0;
            height: 100%;
            border-radius: 0.75rem;
            border: 1px solid rgb(229 231 235);
            background: white;
            overflow: hidden;
        }

        .dark .er-map-wrap {
            border-color: rgb(55 65 81);
            background: rgb(17 24 39);
        }

        .er-map-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            padding: 0.55rem 0.75rem;
            border-bottom: 1px solid rgb(243 244 246);
            flex-shrink: 0;
        }

        .dark .er-map-toolbar {
            border-bottom-color: rgb(55 65 81);
        }

        .er-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem 1rem;
            align-items: center;
        }

        .er-legend-item {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.75rem;
            color: rgb(75 85 99);
        }

        .dark .er-legend-item {
            color: rgb(209 213 219);
        }

        .er-legend-swatch {
            width: 0.8rem;
            height: 0.8rem;
            border-radius: 9999px;
            border: 2px solid rgba(255, 255, 255, 0.9);
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.15);
            flex-shrink: 0;
        }

        .er-legend-line {
            width: 1.1rem;
            height: 0;
            border-top: 3px solid #2563eb;
            border-radius: 2px;
            flex-shrink: 0;
        }

        .er-map-shell {
            position: relative;
            flex: 1 1 auto;
            min-height: 0;
            height: 100%;
            background: rgb(249 250 251);
        }

        .dark .er-map-shell {
            background: rgb(17 24 39);
        }

        #{{ $mapElementId }} {
            width: 100%;
            height: 100%;
            min-height: 280px;
            position: absolute;
            inset: 0;
            z-index: 0;
        }

        #{{ $mapElementId }} .leaflet-container {
            width: 100% !important;
            height: 100% !important;
            z-index: 0;
            font: inherit;
        }

        .er-number-marker {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 9999px;
            background: #ea580c;
            color: white;
            font-size: 12px;
            font-weight: 700;
            border: 2px solid white;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.35);
        }

        .er-number-marker.is-active {
            background: #2563eb;
            transform: scale(1.12);
        }

        .er-endpoint-marker {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 52px;
            height: 24px;
            padding: 0 0.45rem;
            border-radius: 9999px;
            color: white;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.04em;
            border: 2px solid white;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.35);
        }

        .er-endpoint-marker.start {
            background: #16a34a;
        }

        .er-endpoint-marker.end {
            background: #dc2626;
        }

        .er-empty {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 16rem;
            padding: 2rem;
            text-align: center;
            color: rgb(107 114 128);
            font-size: 0.875rem;
        }

        .dark .er-empty {
            color: rgb(156 163 175);
        }

        .er-warning {
            flex-shrink: 0;
            border-radius: 0.75rem;
            border: 1px solid rgb(252 211 77);
            background: rgb(255 251 235);
            color: rgb(120 53 15);
            padding: 0.55rem 0.75rem;
            font-size: 0.8125rem;
        }

        .er-tracking {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
            padding: 0.55rem 0.75rem;
            border-bottom: 1px solid rgb(243 244 246);
            flex-shrink: 0;
        }

        .dark .er-tracking {
            border-bottom-color: rgb(55 65 81);
        }

        .er-mode-switch,
        .er-playback-controls,
        .er-speed-switch,
        .er-playback-stats,
        .er-live-panel {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.4rem;
        }

        .er-mode-btn,
        .er-play-btn,
        .er-speed-btn,
        .er-follow-btn {
            min-height: 2.15rem;
            border-radius: 0.6rem;
            border: 1px solid rgb(209 213 219);
            background: white;
            color: rgb(55 65 81);
            padding: 0.3rem 0.7rem;
            font-size: 0.8125rem;
            font-weight: 650;
        }

        .er-mode-btn.is-active,
        .er-speed-btn.is-active,
        .er-follow-btn {
            background: rgb(37 99 235);
            border-color: rgb(37 99 235);
            color: white;
        }

        .er-play-btn:disabled,
        .er-speed-btn:disabled,
        .er-mode-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .dark .er-mode-btn,
        .dark .er-play-btn,
        .dark .er-speed-btn {
            background: rgb(31 41 55);
            border-color: rgb(75 85 99);
            color: rgb(229 231 235);
        }

        .er-timeline {
            flex: 1 1 10rem;
            min-width: 8rem;
            accent-color: rgb(37 99 235);
        }

        .er-playback-stats {
            font-size: 0.75rem;
            color: rgb(55 65 81);
        }

        .dark .er-playback-stats,
        .dark .er-live-meta {
            color: rgb(209 213 219);
        }

        .er-live-panel {
            align-items: center;
        }

        .er-live-photo,
        .er-live-fallback {
            width: 2.4rem;
            height: 2.4rem;
            border-radius: 9999px;
            object-fit: cover;
            background: rgb(219 234 254);
            flex-shrink: 0;
        }

        .er-live-name {
            font-size: 0.875rem;
            font-weight: 700;
            color: rgb(17 24 39);
        }

        .dark .er-live-name {
            color: white;
        }

        .er-live-meta,
        .er-live-note,
        .er-live-connection {
            font-size: 0.75rem;
            color: rgb(75 85 99);
        }

        .er-status {
            display: inline-flex;
            align-items: center;
            border-radius: 9999px;
            padding: 0.05rem 0.45rem;
            font-size: 0.7rem;
            font-weight: 700;
            background: rgb(229 231 235);
            color: rgb(55 65 81);
        }

        .er-status.moving { background: rgb(220 252 231); color: rgb(22 101 52); }
        .er-status.stopped { background: rgb(254 243 199); color: rgb(146 64 14); }
        .er-status.gps_signal_lost { background: rgb(255 237 213); color: rgb(154 52 18); }
        .er-status.offline { background: rgb(229 231 235); color: rgb(75 85 99); }

        .er-playhead,
        .er-live-dot {
            display: block;
            width: 18px;
            height: 18px;
            border-radius: 9999px;
            background: #2563eb;
            border: 2px solid white;
            box-shadow: 0 1px 6px rgba(0, 0, 0, 0.35);
        }

        .er-live-dot.moving { background: #16a34a; }
        .er-live-dot.stopped { background: #d97706; }
        .er-live-dot.gps_signal_lost { background: #ea580c; }
        .er-live-dot.offline { background: #6b7280; }

        .er-overview-link {
            font-size: 0.75rem;
            color: rgb(37 99 235);
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .dark .er-warning {
            border-color: rgb(146 64 14);
            background: rgb(69 26 3 / 0.35);
            color: rgb(254 243 199);
        }

        @media (max-width: 1023px) {
            .er-page {
                height: auto;
                min-height: 100vh;
                overflow: visible;
            }

            .er-body {
                display: flex;
                flex-direction: column;
                overflow: visible;
            }

            .er-map-wrap {
                order: 1;
                height: auto;
                min-height: 55vh;
            }

            .er-map-shell {
                min-height: 55vh;
            }

            #{{ $mapElementId }} {
                position: relative;
                min-height: 55vh;
            }

            .er-panel {
                order: 2;
                height: auto;
                max-height: none;
            }

            .er-panel-list {
                max-height: 28rem;
            }
        }
    </style>
@endpush

<x-filament-panels::page>
    <div
        class="er-page"
        x-data="{
            stoppagesOnly: false,
            activeId: null,
            selectEvent(id) {
                this.activeId = id;
                window.dispatchEvent(new CustomEvent('er-select-event', { detail: { id } }));
            },
        }"
        @er-marker-selected.window="activeId = $event.detail.id; $nextTick(() => {
            const el = document.querySelector('[data-event-id=\'' + $event.detail.id + '\']');
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        })"
    >
        @if (filled($sparseWarning))
            <div class="er-warning">{{ $sparseWarning }}</div>
        @endif

        <header class="er-header">
            <div class="er-header-top">
                <div class="er-employee">
                    <h1 class="er-employee-name">{{ $employeeName }}</h1>
                    <div class="er-employee-meta">
                        @if (filled($employeeCode))
                            {{ $employeeCode }}
                        @endif
                        @if (filled($employeeCode) && filled($attendanceDate))
                            |
                        @endif
                        {{ $attendanceDate }}
                        <a href="{{ $navigation['list_url'] ?? '#' }}" class="er-back-link">
                            Back to Employee Routes
                        </a>
                    </div>
                </div>

                <div class="er-date-nav">
                    @if (! empty($navigation['previous_url']))
                        <a href="{{ $navigation['previous_url'] }}" class="er-nav-btn" title="Previous date" aria-label="Previous date">
                            <x-filament::icon icon="heroicon-m-chevron-left" class="h-5 w-5" />
                        </a>
                    @else
                        <button type="button" class="er-nav-btn" disabled aria-label="Previous date unavailable">
                            <x-filament::icon icon="heroicon-m-chevron-left" class="h-5 w-5" />
                        </button>
                    @endif

                    <div class="er-date-label">{{ $attendanceDate }}</div>

                    @if (! empty($navigation['next_url']))
                        <a href="{{ $navigation['next_url'] }}" class="er-nav-btn" title="Next date" aria-label="Next date">
                            <x-filament::icon icon="heroicon-m-chevron-right" class="h-5 w-5" />
                        </a>
                    @else
                        <button type="button" class="er-nav-btn" disabled aria-label="Next date unavailable">
                            <x-filament::icon icon="heroicon-m-chevron-right" class="h-5 w-5" />
                        </button>
                    @endif

                    <input
                        type="date"
                        class="er-date-input"
                        value="{{ $attendanceDateIso }}"
                        wire:change="goToDate($event.target.value)"
                        title="Jump to date"
                        aria-label="Jump to date"
                    />

                    <label class="er-toggle">
                        <input
                            type="checkbox"
                            x-model="stoppagesOnly"
                        />
                        Show Only Stoppages
                    </label>
                </div>
            </div>

            <div class="er-summary">
                <div class="er-summary-card">
                    <div class="er-summary-label">Total Distance</div>
                    <div class="er-summary-value">{{ $distanceKm }} KM</div>
                </div>
                <div class="er-summary-card">
                    <div class="er-summary-label">Travel Time</div>
                    <div class="er-summary-value">{{ $travelTimeLabel }}</div>
                </div>
                <div class="er-summary-card">
                    <div class="er-summary-label">Stoppages</div>
                    <div class="er-summary-value">{{ $stopCount }}</div>
                </div>
                <div class="er-summary-card">
                    <div class="er-summary-label">Punch In</div>
                    <div class="er-summary-value">{{ $punchInTime ?: '—' }}</div>
                </div>
                <div class="er-summary-card">
                    <div class="er-summary-label">Punch Out</div>
                    <div class="er-summary-value">{{ $punchOutTime ?: '—' }}</div>
                </div>
            </div>
        </header>

        <div class="er-body">
            <aside class="er-panel" id="{{ $panelElementId }}">
                <div class="er-panel-head">
                    <h2 class="er-panel-title">Route History</h2>
                    <span class="er-panel-count">{{ count($journeyEvents) }} events</span>
                </div>

                <div class="er-panel-list">
                    @forelse ($journeyEvents as $event)
                        @php
                            $type = $event['type'] ?? '';
                            $eventId = $event['id'] ?? '';
                            $isStoppage = $type === 'stoppage';
                            $isTravel = $type === 'travel';
                            $isEndpoint = in_array($type, ['start', 'end'], true);
                            $clickable = $isStoppage || ($isEndpoint && ($event['latitude'] ?? null) !== null);
                        @endphp

                        @if ($isTravel)
                            <div
                                class="er-event-travel"
                                data-event-id="{{ $eventId }}"
                                data-event-type="travel"
                                x-show="!stoppagesOnly"
                                x-cloak
                            >
                                <div class="er-travel-title">Travel 🚗</div>
                                <div class="er-event-time">{{ $event['time_label'] ?? '—' }}</div>
                                @if (! empty($event['distance_km']))
                                    <div class="er-event-meta">{{ number_format((float) $event['distance_km'], 1) }} KM
                                        @if (! empty($event['duration_label']))
                                            · {{ $event['duration_label'] }}
                                        @endif
                                    </div>
                                @elseif (! empty($event['duration_label']))
                                    <div class="er-event-meta">{{ $event['duration_label'] }}</div>
                                @endif
                            </div>
                        @else
                            <button
                                type="button"
                                class="er-event"
                                data-event-id="{{ $eventId }}"
                                data-event-type="{{ $type }}"
                                data-clickable="{{ $clickable ? '1' : '0' }}"
                                data-sequence="{{ $event['sequence'] ?? '' }}"
                                @if ($isStoppage)
                                    x-show="true"
                                @elseif ($isEndpoint)
                                    x-show="!stoppagesOnly"
                                    x-cloak
                                @endif
                                @class(['is-active' => false])
                                :class="{ 'is-active': activeId === @js($eventId) }"
                                @if ($clickable)
                                    @click="selectEvent(@js($eventId))"
                                @endif
                            >
                                @if ($type === 'start')
                                    <span class="er-event-badge start">START</span>
                                @elseif ($type === 'end')
                                    <span class="er-event-badge end">END</span>
                                @else
                                    <span class="er-event-badge">{{ $event['sequence'] }}</span>
                                @endif

                                <div class="er-event-body">
                                    <div class="er-event-title">
                                        @if ($isEndpoint)
                                            {{ $event['label'] }}
                                        @else
                                            {{ $event['label'] ?? 'Stop' }}
                                        @endif
                                    </div>
                                    @if (filled($event['location'] ?? null))
                                        <div class="er-event-location" title="{{ $event['location'] }}">
                                            {{ $event['location'] }}
                                        </div>
                                    @endif
                                    <div class="er-event-time">{{ $event['time_label'] ?? '—' }}</div>
                                    @if (! empty($event['duration_label']))
                                        <div class="er-event-meta">Duration: {{ $event['duration_label'] }}</div>
                                    @endif
                                </div>
                            </button>
                        @endif
                    @empty
                        <div class="er-empty">No route events recorded for this day.</div>
                    @endforelse
                </div>
            </aside>

            <section class="er-map-wrap">
                <div class="er-map-toolbar">
                    <h2 class="er-panel-title" style="text-transform:none; letter-spacing:0;">Route Map</h2>
                    <div class="er-legend">
                        <div class="er-legend-item">
                            <span class="er-legend-swatch" style="background:#16a34a;"></span>
                            <span>Start</span>
                        </div>
                        <div class="er-legend-item">
                            <span class="er-legend-swatch" style="background:#ea580c;"></span>
                            <span>Stoppage</span>
                        </div>
                        <div class="er-legend-item">
                            <span class="er-legend-swatch" style="background:#dc2626;"></span>
                            <span>End</span>
                        </div>
                        <div class="er-legend-item">
                            <span class="er-legend-line" aria-hidden="true"></span>
                            <span>Travel path</span>
                        </div>
                    </div>
                </div>

                <div class="er-tracking" id="{{ $mapElementId }}-ui">
                    <div class="er-mode-switch" role="tablist" aria-label="Route mode">
                        <button type="button" class="er-mode-btn is-active" data-er-mode="playback" aria-pressed="true">Route Playback</button>
                        <button type="button" class="er-mode-btn" data-er-mode="live" aria-pressed="false">Live Tracking</button>
                    </div>

                    <div data-er-panel="playback">
                        @unless ($hasEnoughPoints)
                            <div class="er-live-note">Not enough valid route points to play this day. At least 2 valid points are required.</div>
                        @endunless
                        <div class="er-playback-controls">
                            <button type="button" class="er-play-btn" data-er-play>Play</button>
                            <button type="button" class="er-play-btn" data-er-pause>Pause</button>
                            <button type="button" class="er-play-btn" data-er-resume>Resume</button>
                            <button type="button" class="er-play-btn" data-er-restart>Restart</button>
                            <button type="button" class="er-play-btn" data-er-stop>Stop</button>
                            <div class="er-speed-switch" role="group" aria-label="Playback speed">
                                <button type="button" class="er-speed-btn is-active" data-er-speed="1">1x</button>
                                <button type="button" class="er-speed-btn" data-er-speed="2">2x</button>
                                <button type="button" class="er-speed-btn" data-er-speed="5">5x</button>
                                <button type="button" class="er-speed-btn" data-er-speed="10">10x</button>
                            </div>
                        </div>
                        <input class="er-timeline" data-er-timeline type="range" min="0" max="1000" value="0" aria-label="Route timeline" />
                        <div class="er-playback-stats">
                            <span data-er-stat="time">—</span>
                            <span data-er-stat="distance">0 km</span>
                            <span data-er-stat="speed">0 km/h</span>
                            <span data-er-stat="stops">0 stoppages</span>
                        </div>
                    </div>

                    <div data-er-panel="live" hidden>
                        <img class="er-live-photo" data-er-live="photo" alt="" hidden />
                        <div>
                            <div class="er-live-name" data-er-live="name">{{ $employeeName }}</div>
                            <div class="er-live-meta">
                                <span class="er-status offline" data-er-live="status">Offline</span>
                                <span data-er-live="speed">Current speed: —</span>
                            </div>
                            <div class="er-live-meta" data-er-live="updated">Last GPS update: —</div>
                            <div class="er-live-note" data-er-live="note"></div>
                            <div class="er-live-connection" data-er-live="connection"></div>
                        </div>
                        <button type="button" class="er-follow-btn" data-er-follow>Following</button>
                        <a class="er-overview-link" href="{{ $routeData['live']['overview_url'] ?? '#' }}">All live employees</a>
                    </div>
                </div>

                <div class="er-map-shell">
                    <div id="{{ $mapElementId }}" wire:ignore></div>
                </div>
            </section>
        </div>
    </div>

    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""
    ></script>
    <script src="{{ asset('js/employee-route-tracking.js') }}?v=20261122"></script>
    @script
        <script>
            window.EmployeeRouteTracking.mountRoute({
                mapElementId: @js($mapElementId),
                routeData: @json($routeData, JSON_THROW_ON_ERROR),
            });
        </script>
    @endscript
</x-filament-panels::page>

