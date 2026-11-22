<x-filament-panels::page>
    @push('styles')
        <link
            rel="stylesheet"
            href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
            integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
            crossorigin=""
        />
        <style>
            .lv-shell {
                display: grid;
                grid-template-columns: minmax(16rem, 22rem) minmax(0, 1fr);
                gap: 0.75rem;
                min-height: calc(100vh - 12rem);
            }

            .lv-list {
                border: 1px solid rgb(229 231 235);
                border-radius: 0.75rem;
                background: white;
                overflow: auto;
                max-height: calc(100vh - 12rem);
                padding: 0.4rem;
            }

            .lv-map {
                position: relative;
                min-height: 28rem;
                height: calc(100vh - 12rem);
                border: 1px solid rgb(229 231 235);
                border-radius: 0.75rem;
                overflow: hidden;
            }

            .lv-map .leaflet-container {
                width: 100%;
                height: 100%;
                font: inherit;
            }

            .lv-person {
                display: flex;
                gap: 0.6rem;
                align-items: center;
                padding: 0.55rem 0.45rem;
                border-radius: 0.6rem;
                text-decoration: none;
                color: inherit;
            }

            .lv-person:hover { background: rgb(239 246 255); }

            .lv-person strong,
            .lv-person small {
                display: block;
            }

            .lv-person small { color: rgb(75 85 99); font-size: 0.75rem; }

            .lv-badge,
            .lv-marker {
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .lv-badge {
                width: 2rem;
                height: 2rem;
                border-radius: 9999px;
                color: white;
                font-weight: 700;
                background: #6b7280;
                flex-shrink: 0;
            }

            .lv-badge.moving, .lv-marker.moving b { background: #16a34a; }
            .lv-badge.stopped, .lv-marker.stopped b { background: #d97706; }
            .lv-badge.gps_signal_lost, .lv-marker.gps_signal_lost b { background: #ea580c; }
            .lv-badge.offline, .lv-marker.offline b { background: #6b7280; }

            .lv-marker {
                gap: 0.35rem;
                width: max-content;
            }

            .lv-marker b {
                width: 28px;
                height: 28px;
                border-radius: 9999px;
                border: 2px solid white;
                color: white;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
            }

            .lv-marker span {
                background: white;
                border-radius: 9999px;
                padding: 0.1rem 0.4rem;
                font-size: 11px;
                font-weight: 700;
                color: rgb(17 24 39);
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
                max-width: 8rem;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .lv-empty {
                margin: 0.75rem;
                color: rgb(107 114 128);
                font-size: 0.875rem;
            }

            @media (max-width: 800px) {
                .lv-shell { grid-template-columns: 1fr; }
                .lv-list { max-height: 16rem; order: 2; }
                .lv-map { height: 60vh; order: 1; }
            }
        </style>
    @endpush

    <div class="lv-shell">
        <aside class="lv-list" id="live-employee-list">
            <p class="lv-empty">Loading punched-in employees…</p>
        </aside>
        <div class="lv-map">
            <div id="live-employee-map" style="width:100%;height:100%;"></div>
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
            window.EmployeeRouteTracking.mountOverview({
                mapElementId: 'live-employee-map',
                listElementId: 'live-employee-list',
                overviewUrl: @js($this->overviewUrl()),
            });
        </script>
    @endscript
</x-filament-panels::page>
