<x-filament-panels::page>
    <div class="space-y-6">
        <form wire:submit.prevent="mount" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            {{ $this->form }}
        </form>
        <div class="-mx-4 sm:mx-0 overflow-x-auto pb-2">
            <div class="min-w-[900px] lg:min-w-full">
                {{ $this->table }}
            </div>
        </div>
    </div>
    @push('styles')
        <style>
            @media (max-width: 1280px){
                body .fi-layout { --report-table-max-w: 100vw; }
            }
        </style>
    @endpush
</x-filament-panels::page>
