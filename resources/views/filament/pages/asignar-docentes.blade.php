<x-filament-panels::page>

   <form>
    {{ $this->form }}
</form>

    

    @if ($plazaSeleccionada)
    {{-- Usamos un grid para colocar las tarjetas una al lado de la otra --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

        {{-- Card 1: Vacantes Totales --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-x-4">
                {{-- Círculo para el icono --}}
                <div class="flex-shrink-0 rounded-full bg-gray-100 p-3 dark:bg-gray-700">
                    <x-heroicon-o-users class="h-6 w-6 text-gray-500 dark:text-gray-400" />
                </div>
                {{-- Textos --}}
                <div class="flex-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Vacantes Totales</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                        {{ $plazaSeleccionada->loccar_iVacante }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Card 2: Plazas Ocupadas --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-x-4">
                <div class="flex-shrink-0 rounded-full bg-gray-100 p-3 dark:bg-gray-700">
                    <x-heroicon-o-user-group class="h-6 w-6 text-gray-500 dark:text-gray-400" />
                </div>
                <div class="flex-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Plazas Ocupadas</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                        {{ $plazaSeleccionada->loccar_iOcupado }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Card 3: Disponibles (Resaltada con el color primario) --}}
        <div class="rounded-xl border border-primary-500 bg-primary-50 p-4 shadow-sm dark:border-primary-500 dark:bg-gray-800/50">
            <div class="flex items-center gap-x-4">
                <div class="flex-shrink-0 rounded-full bg-primary-100 p-3 dark:bg-primary-500/20">
                    <x-heroicon-o-user-plus class="h-6 w-6 text-primary-600 dark:text-primary-400" />
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-primary-600 dark:text-primary-400">Disponibles</p>
                    <p class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                        {{ $plazaSeleccionada->loccar_iVacante - $plazaSeleccionada->loccar_iOcupado }}
                    </p>
                </div>
            </div>
        </div>

    </div>
@endif
    

    

    @livewire('asignados-docente-table')

    <script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('busqueda_docente');
    const hidden = document.getElementById('docente_id');

    function syncDocenteId() {
        const value = input.value;
        // Busca el código al final del texto: "NOMBRE - DNI - CODIGO"
        const match = value.match(/- ([A-Z0-9]+)$/);
        if (match) {
            hidden.value = match[1];
        } else {
            hidden.value = '';
        }
        hidden.dispatchEvent(new Event('input', { bubbles: true }));
    }

    if (input && hidden) {
        input.addEventListener('input', syncDocenteId);
        input.addEventListener('change', syncDocenteId);
        input.addEventListener('blur', syncDocenteId);
    }
});
</script>
</x-filament-panels::page>