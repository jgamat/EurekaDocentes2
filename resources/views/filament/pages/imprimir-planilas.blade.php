<x-filament-panels::page>
	<div class="space-y-6">
		{{ $this->form }}

		@if(($filters['proceso_id'] ?? false) && ($filters['proceso_fecha_id'] ?? false) && ($filters['tipo_id'] ?? false))
			<div class="filament-box p-4 space-y-2">
				<h2 class="text-lg font-semibold">Personal asignado apto para planilla</h2>
				{{ $this->table }}
			</div>
		@endif
	</div>
</x-filament-panels::page>
