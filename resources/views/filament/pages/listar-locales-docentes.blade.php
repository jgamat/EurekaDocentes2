@php /** @var \\App\\Filament\\Pages\\ListarLocalesDocentes $this */ @endphp
<x-filament::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow">
            <form wire:submit.prevent="noop" class="grid gap-4 md:grid-cols-3">
                {{ $this->form }}
            </form>
        </div>
        <div>
            {{ $this->table }}
        </div>
    </div>
</x-filament::page>
