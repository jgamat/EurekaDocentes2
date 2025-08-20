<x-filament-panels::page>
    {{-- Parte 1: El formulario para seleccionar fecha y añadir nuevos locales --}}
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            {{ $this->getSaveAction }}
        </div>
    </form>

    {{--
        Parte 2: La tabla de locales ya asignados.
        Solo se muestra si hay una fecha seleccionada en el formulario.
        Usamos la propiedad 'data' que definimos antes.
    --}}
    @if (data_get($this->data, 'proceso_fecha_id'))
        <div class="mt-12 border-t pt-8">
            {{-- Esto renderiza la tabla que definimos en el método table() --}}
            {{ $this->table }}
        </div>
    @endif

</x-filament-panels::page>