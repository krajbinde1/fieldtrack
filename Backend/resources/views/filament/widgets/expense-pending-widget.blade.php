@php($pending = $this->pending())
<div class="fi-wi-widget">
    <a href="{{ $pending['url'] }}" class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3">
        <span class="text-sm font-medium text-gray-700">Pending expense approvals</span>
        <span class="text-lg font-semibold text-violet-700">{{ $pending['count'] }}</span>
    </a>
</div>
