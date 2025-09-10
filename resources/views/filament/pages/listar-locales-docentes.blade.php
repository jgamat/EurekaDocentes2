@php /** @var \\App\\Filament\\Pages\\ListarLocalesDocentes $this */ @endphp
<x-filament::page>
    <style>
        @media print {
            body * { visibility: hidden; }
            #print-area, #print-area * { visibility: visible; }
            #print-area { position:absolute; inset:0; width:100%; }
        }
    </style>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow">
            <form wire:submit.prevent="noop" class="grid gap-4 md:grid-cols-3">
                {{ $this->form }}
            </form>
        </div>
        <div id="print-area">
            {{ $this->table }}
        </div>
    </div>
</x-filament::page>
