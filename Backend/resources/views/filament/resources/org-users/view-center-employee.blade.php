<x-filament-panels::page>
    <dl class="grid gap-4 sm:grid-cols-2">
        <div>
            <dt class="text-sm text-gray-500">Employee Code</dt>
            <dd class="text-base font-medium">{{ $this->employee->employee_code ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-sm text-gray-500">Name</dt>
            <dd class="text-base font-medium">{{ $this->employee->full_name }}</dd>
        </div>
        <div>
            <dt class="text-sm text-gray-500">Designation</dt>
            <dd class="text-base font-medium">{{ $this->employee->designation ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-sm text-gray-500">Mobile</dt>
            <dd class="text-base font-medium">{{ $this->employee->mobile ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-sm text-gray-500">Center</dt>
            <dd class="text-base font-medium">{{ $this->employee->center?->name ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-sm text-gray-500">Status</dt>
            <dd class="text-base font-medium">{{ $this->employee->status ? 'Active' : 'Inactive' }}</dd>
        </div>
    </dl>
</x-filament-panels::page>
