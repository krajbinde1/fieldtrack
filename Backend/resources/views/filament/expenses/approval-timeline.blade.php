@php
    $timeline = $getState();
    $stages = is_array($timeline) ? ($timeline['stages'] ?? []) : [];
    $pendingText = is_array($timeline) ? ($timeline['pending_text'] ?? null) : null;

    $icon = function (string $color): string {
        if ($color === 'green') {
            return '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 10.5l3.2 3.2L15 6.8" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        }
        if ($color === 'red') {
            return '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M6.5 6.5l7 7M13.5 6.5l-7 7" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>';
        }
        if ($color === 'orange') {
            return '<svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="5.5" fill="none" stroke="#fff" stroke-width="1.7"/><path d="M10 7.2V10l2 1.4" fill="none" stroke="#fff" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        }

        return '<span class="expense-timeline-hollow"></span>';
    };
@endphp

<div class="expense-timeline">
    @if (filled($pendingText))
        <p class="expense-timeline-pending">{{ $pendingText }}</p>
    @endif

    @foreach ($stages as $index => $stage)
        @php
            $color = in_array($stage['color'] ?? '', ['green', 'orange', 'red'], true) ? $stage['color'] : 'grey';
            $lineColor = in_array($color, ['green', 'red'], true) ? $color : 'grey';
            $isLast = $index === count($stages) - 1;
        @endphp
        <div class="expense-timeline-item">
            <div class="expense-timeline-rail">
                <span class="expense-timeline-dot expense-timeline-dot-{{ $color }}">{!! $icon($color) !!}</span>
                @unless ($isLast)
                    <span class="expense-timeline-line expense-timeline-line-{{ $lineColor }}"></span>
                @endunless
            </div>
            <div class="expense-timeline-body">
                <div class="expense-timeline-head">
                    <div class="expense-timeline-title">{{ $stage['title'] ?? '' }}</div>
                    <span class="expense-timeline-badge expense-timeline-badge-{{ $color }}">{{ $stage['status_label'] ?? '' }}</span>
                </div>
                @if (filled($stage['person_name'] ?? null))
                    <div class="expense-timeline-person">{{ $stage['person_name'] }}</div>
                @endif
                @if (filled($stage['role'] ?? null))
                    <div class="expense-timeline-role">{{ $stage['role'] }}</div>
                @endif
                @if (filled($stage['date'] ?? null) || filled($stage['time'] ?? null))
                    <div class="expense-timeline-when">
                        {{ $stage['date'] ?? '' }}@if (filled($stage['date'] ?? null) && filled($stage['time'] ?? null)) · @endif{{ $stage['time'] ?? '' }}
                    </div>
                @endif
                @if (filled($stage['detail'] ?? null))
                    <div class="expense-timeline-detail">{{ $stage['detail'] }}</div>
                @endif
                @if (filled($stage['remarks'] ?? null))
                    <div class="expense-timeline-remarks">{{ $stage['remarks'] }}</div>
                @endif
            </div>
        </div>
    @endforeach
</div>

<style>
    .expense-timeline { display: flex; flex-direction: column; gap: 0; color: #0f172a; }
    .expense-timeline-pending {
        margin: 0 0 14px;
        padding: 8px 12px;
        border-radius: 10px;
        background: #fff7ed;
        color: #c2410c;
        font-size: 13px;
        font-weight: 600;
    }
    .expense-timeline-item { display: flex; gap: 12px; }
    .expense-timeline-rail { display: flex; flex-direction: column; align-items: center; width: 28px; flex: none; }
    .expense-timeline-dot {
        width: 28px; height: 28px; border-radius: 999px;
        display: flex; align-items: center; justify-content: center; flex: none;
    }
    .expense-timeline-dot svg { width: 16px; height: 16px; display: block; }
    .expense-timeline-dot-green { background: #16a34a; }
    .expense-timeline-dot-orange { background: #f97316; }
    .expense-timeline-dot-red { background: #dc2626; }
    .expense-timeline-dot-grey { background: #fff; border: 2px solid #9ca3af; }
    .expense-timeline-hollow { width: 8px; height: 8px; border-radius: 999px; background: #9ca3af; }
    .expense-timeline-line { width: 2px; flex: 1; min-height: 18px; margin: 4px 0; }
    .expense-timeline-line-green { background: #16a34a; }
    .expense-timeline-line-red { background: #dc2626; }
    .expense-timeline-line-grey { background: #d1d5db; }
    .expense-timeline-body { flex: 1; padding-bottom: 18px; min-width: 0; }
    .expense-timeline-head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
    .expense-timeline-title { font-weight: 700; font-size: 14px; line-height: 1.3; }
    .expense-timeline-person { margin-top: 2px; font-size: 14px; font-weight: 600; }
    .expense-timeline-role, .expense-timeline-when, .expense-timeline-detail, .expense-timeline-remarks {
        margin-top: 2px; font-size: 13px; color: #64748b;
    }
    .expense-timeline-badge {
        flex: none; font-size: 12px; font-weight: 700; line-height: 1;
        padding: 5px 8px; border-radius: 999px;
    }
    .expense-timeline-badge-green { background: #ecfdf5; color: #047857; }
    .expense-timeline-badge-orange { background: #fff7ed; color: #c2410c; }
    .expense-timeline-badge-red { background: #fef2f2; color: #b91c1c; }
    .expense-timeline-badge-grey { background: #f3f4f6; color: #6b7280; }
</style>
