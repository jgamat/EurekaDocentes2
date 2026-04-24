<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <form wire:submit.prevent="mount" class="mx-auto max-w-7xl grid grid-cols-1 gap-6">
                {{ $this->form }}
            </form>
        </div>

        <div class="overflow-x-auto">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
