<div x-data="{ procesoId: @entangle('proceso_id'), fechaId: @entangle('proceso_fecha_id') }" class="ctx-switcher">
    <div class="ctx-field">
    <select wire:model="proceso_id" wire:change="changeProceso($event.target.value)" class="ctx-select">
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
    <div class="ctx-field">
    <select wire:key="fecha-{{ $fechaSelectVersion }}" wire:model="proceso_fecha_id" wire:change="changeFecha($event.target.value)" class="ctx-select" @if(empty($fechaOptions)) disabled @endif>
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
        x-bind:class="(!procesoId || !fechaId) ? 'ctx-apply-disabled' : ''"
        wire:click.prevent="apply"
        type="button"
        class="ctx-apply"
    >Aplicar</button>
</div>
