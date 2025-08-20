<div>
    @if($resultados->count() > 0)
        <div class="space-y-2">
            @foreach($resultados as $docente)
                {{-- Cada botón llama al método público 'seleccionarDocente' en la página --}}
                <x-filament::button
                    color="secondary"
                    outlined
                    wire:click.prevent="$dispatch('docenteSeleccionadoDesdeModal', { docenteId: {{ $docente->doc_id }} })"
                    class="w-full"
                >
                    {{ $docente->nombre_completo }} - {{ $docente->doc_vcDni }}
                </x-filament::button>
            @endforeach
        </div>
    @else
        <div class="text-center text-gray-500">
            No se encontraron docentes con ese criterio.
        </div>
    @endif
</div>