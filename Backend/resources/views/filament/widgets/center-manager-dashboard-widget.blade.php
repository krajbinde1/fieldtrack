@php
    $dash = $this->dashboard();
    $chartMax = max(1, ...array_map(
        fn (array $bar): int => max($bar['target'], $bar['achieved']),
        $dash['chart'] === [] ? [['target' => 0, 'achieved' => 0]] : $dash['chart'],
    ));
@endphp

<x-filament-widgets::widget>
    <div class="ft-cm-dash">
        <header class="ft-cm-head">
            <div>
                <p class="ft-cm-kicker">Center Manager</p>
                <h2 class="ft-cm-name">{{ $dash['manager'] }}</h2>
                <p class="ft-cm-meta">{{ $dash['selectedCenter'] }}</p>
            </div>
            <p class="ft-cm-date">{{ $dash['todayLabel'] }}</p>
        </header>

        <div class="ft-cm-filters">
            @foreach (['today' => 'Today', 'this_week' => 'This Week', 'this_month' => 'This Month', 'custom' => 'Custom'] as $key => $label)
                <button type="button" wire:click="setPreset('{{ $key }}')" @class(['ft-cm-chip', 'is-active' => $preset === $key])>{{ $label }}</button>
            @endforeach
            @if ($dash['showCenterSelect'])
                <label class="ft-cm-center">
                    <span>Center</span>
                    <select wire:model.live="centerId">
                        <option value="all">All Assigned Centers</option>
                        @foreach ($dash['centerOptions'] as $center)
                            <option value="{{ $center['id'] }}">{{ $center['name'] }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            @if ($preset === 'custom')
                <label class="ft-cm-center">
                    <span>From</span>
                    <input type="date" wire:model.live="from">
                </label>
                <label class="ft-cm-center">
                    <span>To</span>
                    <input type="date" wire:model.live="to">
                </label>
            @endif
        </div>

        <div class="ft-cm-kpis">
            @foreach ($dash['cards'] as $card)
                <a href="{{ $card['url'] }}" class="ft-dash-card ft-dash-card--{{ $card['tone'] }} is-link">
                    @include('filament.widgets.partials.dashboard-stat-card-inner', ['card' => $card])
                </a>
            @endforeach
        </div>

        <div class="ft-cm-grid">
            <section class="ft-cm-panel">
                <div class="ft-cm-panel-head">
                    <h3>Admission Target vs Achievement</h3>
                    <span>{{ $dash['periodLabel'] }}</span>
                </div>
                <div class="ft-cm-chart">
                    @forelse ($dash['chart'] as $bar)
                        <div class="ft-cm-bar">
                            <span class="ft-cm-bar-label">{{ $bar['label'] }}</span>
                            <span class="ft-cm-track" title="Target {{ $bar['target'] }}">
                                <span class="ft-cm-fill is-target" style="width: {{ (int) round(($bar['target'] / $chartMax) * 100) }}%"></span>
                            </span>
                            <span class="ft-cm-track" title="Achieved {{ $bar['achieved'] }}">
                                <span class="ft-cm-fill is-achieved" style="width: {{ (int) round(($bar['achieved'] / $chartMax) * 100) }}%"></span>
                            </span>
                            <span class="ft-cm-bar-n">{{ $bar['achieved'] }}/{{ $bar['target'] }}</span>
                        </div>
                    @empty
                        <p class="ft-cm-empty">No target data for this period.</p>
                    @endforelse
                </div>
                <p class="ft-cm-legend"><span class="is-target"></span> Target <span class="is-achieved"></span> Confirmed achievement</p>
            </section>

            <section class="ft-cm-panel">
                <div class="ft-cm-panel-head">
                    <h3>Employee Attendance Today</h3>
                    <a href="{{ $dash['attendanceUrl'] }}">View attendance</a>
                </div>
                <div class="ft-cm-table-wrap">
                    <table class="ft-cm-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Punch in</th>
                                <th>Punch out</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($dash['attendanceRows'] as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $row['punch_in'] }}</td>
                                    <td>{{ $row['punch_out'] }}</td>
                                    <td>{{ $row['status'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="ft-cm-empty">No employees in the selected centers.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="ft-cm-panel">
                <div class="ft-cm-panel-head">
                    <h3>Pending Admissions for Approval</h3>
                    <a href="{{ $dash['pendingAdmissionsUrl'] }}">View all</a>
                </div>
                @forelse ($dash['pendingAdmissions'] as $row)
                    <div class="ft-cm-row">
                        <strong>{{ $row['name'] }}</strong>
                        <span>{{ $row['employee'] }} · {{ $row['center'] }}</span>
                        <em>{{ $row['when'] }}</em>
                    </div>
                @empty
                    <p class="ft-cm-empty">No admissions are waiting for approval.</p>
                @endforelse
            </section>

            <section class="ft-cm-panel">
                <div class="ft-cm-panel-head">
                    <h3>Pending Leave Requests</h3>
                    <a href="{{ $dash['pendingLeavesUrl'] }}">View all</a>
                </div>
                @forelse ($dash['pendingLeaves'] as $row)
                    <div class="ft-cm-row">
                        <strong>{{ $row['name'] }}</strong>
                        <span>{{ $row['type'] }}</span>
                        <em>{{ $row['dates'] }}</em>
                    </div>
                @empty
                    <p class="ft-cm-empty">No leave requests are pending.</p>
                @endforelse
            </section>

            <section class="ft-cm-panel">
                <div class="ft-cm-panel-head">
                    <h3>Recent Employee Activities</h3>
                </div>
                @forelse ($dash['activities'] as $row)
                    <div class="ft-cm-row">
                        <strong>{{ $row['name'] }}</strong>
                        <span>{{ $row['activity'] }}</span>
                        <em>{{ $row['when'] }}</em>
                    </div>
                @empty
                    <p class="ft-cm-empty">No field activities in this period.</p>
                @endforelse
            </section>

            <section class="ft-cm-panel ft-cm-shortcut">
                <div>
                    <h3>Employee Route Tracking</h3>
                    <p>Open today’s routes for the selected centers.</p>
                </div>
                <a class="ft-cm-shortcut-link" href="{{ $dash['routesUrl'] }}">Open route tracking</a>
            </section>
        </div>
    </div>
</x-filament-widgets::widget>
