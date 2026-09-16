<x-filament-panels::page>
    <div class="space-y-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Each Director, Project Head, Center Manager, and Employee mobile login is bound to one registered device.
            Use Reset Device when a user needs to change phones. Admin web login is not device-locked.
        </p>
        {{ $this->table }}
    </div>
</x-filament-panels::page>
