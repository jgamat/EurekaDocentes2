<x-filament-panels::page>
	<form>
		{{ $this->form }}
	</form>

	@if ($plazaSeleccionada)
		<div class="grid grid-cols-1 gap-4 sm:grid-cols-3 mt-6">
			<div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
				<div class="text-sm text-gray-500">Vacantes</div>
				<div class="text-2xl font-semibold">{{ $plazaSeleccionada->loccar_iVacante ?? '-' }}</div>
			</div>
			<div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
				<div class="text-sm text-gray-500">Ocupadas</div>
				<div class="text-2xl font-semibold">{{ $plazaSeleccionada->loccar_iOcupado ?? '-' }}</div>
			</div>
			<div class="rounded-xl border border-primary-500 bg-primary-50 p-4 shadow-sm dark:border-primary-500 dark:bg-gray-800/50">
				<div class="text-sm text-primary-700">Disponibles</div>
				<div class="text-2xl font-semibold text-primary-700">{{ ($plazaSeleccionada->loccar_iVacante ?? 0) - ($plazaSeleccionada->loccar_iOcupado ?? 0) }}</div>
			</div>
		</div>
	@endif

	@livewire('asignados-alumno-table')

	<script>
	document.addEventListener('DOMContentLoaded', function () {
		const input = document.getElementById('busqueda_alumno');
		const hidden = document.getElementById('alumno_codigo');

		function syncCodigo() {
			const value = input.value;
			// Formato: "APELLIDOS NOMBRES - DNI - CÓDIGO"
			const match = value.match(/-\s*(\S+)$/);
			hidden.value = match ? match[1] : '';
			hidden.dispatchEvent(new Event('input', { bubbles: true }));
			hidden.dispatchEvent(new Event('change', { bubbles: true }));
		}

		if (input && hidden) {
			input.addEventListener('change', syncCodigo);
			input.addEventListener('blur', syncCodigo);
		}
	});
	</script>
</x-filament-panels::page>
