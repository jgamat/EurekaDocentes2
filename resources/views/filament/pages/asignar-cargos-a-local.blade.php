<x-filament-panels::page>
    {{-- Formulario para seleccionar local y añadir nuevos cargos --}}
    <form wire:submit="save">
        {{ $this->form }}
        <div class="mt-6">
            {{ $this->getSaveAction }}
        </div>
    </form>

    {{-- Renderizamos nuestro nuevo componente de tabla de Livewire --}}
    {{-- Este componente escuchará los eventos y se mostrará/actualizará solo --}}
    @livewire('cargos-asignados-table')

</x-filament-panels::page>