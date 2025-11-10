<div x-data="{ procesoId: @entangle('proceso_id'), fechaId: @entangle('proceso_fecha_id') }" class="flex items-center gap-2">
    <div>
    <select wire:model="proceso_id" wire:change="changeProceso($event.target.value)" class="fi-input rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm">
            @if(empty($procesoOptions))
                <option value="">— Sin procesos abiertos —</option>
            @else
                <option value="">— Seleccione proceso —</option>
            @endif
            @foreach($procesoOptions as $id => $label)
                <option value="{{ $id }}" @if($proceso_id == $id) selected @endif>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
    <select wire:key="fecha-{{ $fechaSelectVersion }}" wire:model="proceso_fecha_id" wire:change="changeFecha($event.target.value)" class="fi-input rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm" @if(empty($fechaOptions)) disabled @endif>
            @if(empty($fechaOptions))
                <option value="">— Sin fechas activas —</option>
            @else
                <option value="">— Seleccione fecha —</option>
            @endif
            @foreach($fechaOptions as $id => $label)
                <option value="{{ $id }}" @if($proceso_fecha_id == $id) selected @endif>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <button
        x-bind:disabled="!procesoId || !fechaId"
        x-bind:class="(!procesoId || !fechaId) ? 'opacity-50 cursor-not-allowed' : ''"
        wire:click.prevent="apply"
        type="button"
        class="px-3 py-1.5 rounded-md bg-primary-600 text-white text-sm hover:bg-primary-700 transition-colors"
    >Aplicar</button>
</div>
