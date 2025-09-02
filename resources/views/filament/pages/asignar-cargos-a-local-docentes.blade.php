<x-filament-panels::page>
	<form wire:submit="save" class="space-y-6">
		{{ $this->form }}
		@if($filters['local_id'] ?? false)
			<div>
				<x-filament::button type="submit">Guardar asignaciones</x-filament::button>
			</div>
		@endif
	</form>

	@if(($filters['local_id'] ?? false))
		@if(($this->mostrarTabla && $this->hasDocentesAsignados()))
			<div class="filament-box p-4 space-y-2 mt-6">
				<h2 class="text-lg font-semibold">Cargos asignados al local</h2>
				{{ $this->table }}
			</div>
		@else
			<div class="filament-box p-4 space-y-2 mt-6">
				<h2 class="text-lg font-semibold">Cargos asignados al local</h2>
				<div class="text-sm text-gray-600">Aún no hay cargos docentes asignados para este local. Ingrese vacantes y guarde para verlos aquí.</div>
			</div>
		@endif
	@endif
</x-filament-panels::page>
