<x-filament-panels::page>
	<div class="space-y-6">
		<div>
			{{ $this->form }}
		</div>
		@if($filters['local_id'] ?? false)
			<div class="filament-box p-4 space-y-2">
				<h2 class="text-lg font-semibold">Cargos Docentes (2,3,4) Asignados al Local</h2>
				{{ $this->table }}
			</div>
		@endif
	</div>
</x-filament-panels::page>
