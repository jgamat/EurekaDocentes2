<x-filament-panels::page>
    <div class="space-y-6">
        <form wire:submit.prevent="mount" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            {{ $this->form }}
        </form>
        {{ $this->table }}
    </div>
</x-filament-panels::page>
