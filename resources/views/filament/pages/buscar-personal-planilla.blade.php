@php /** @var \App\Filament\Pages\BuscarPersonalPlanilla $this */ @endphp
<x-filament-panels::page>
	<div class="space-y-4" x-data="{ procesoId: @entangle('proceso_id'), fechaId: @entangle('proceso_fecha_id'), }">
		<div class="grid grid-cols-1 md:grid-cols-5 gap-3">
			<!-- Placeholder de fecha actual (solo lectura) -->
			<div class="col-span-1 flex flex-col justify-end">
				<label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Fecha actual</label>
				<div class="px-3 py-2 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm text-gray-800 dark:text-gray-100 min-h-[42px] flex items-center">
					@php
						$fechaLabel = '-';
						if ($this->proceso_fecha_id) {
							$f = \App\Models\ProcesoFecha::find($this->proceso_fecha_id);
							if ($f && $f->profec_dFecha) {
								try { $fechaLabel = \Carbon\Carbon::parse($f->profec_dFecha)->format('d/m/Y'); } catch (Exception $e) { $fechaLabel = (string)$this->proceso_fecha_id; }
							}
						}
					@endphp
					{{ $fechaLabel }}
				</div>
			</div>
			<!-- Selects ocultos: mantenemos binding para compatibilidad -->
			<div class="hidden">
				<select wire:model="proceso_id"></select>
			</div>
			<div class="hidden">
				<select wire:model="proceso_fecha_id"></select>
			</div>
			<div>
				<label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Tipo de planilla</label>
				<select wire:model="tipo" class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
					<option value="docente">Docente</option>
					<option value="administrativo">Administrativo</option>
					<option value="tercero_cas">Tercero/CAS</option>
					<option value="alumno">Alumno</option>
				</select>
			</div>
			<div class="md:col-span-2 flex items-end gap-2">
				<div class="flex-1">
					<label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Búsqueda</label>
					<input type="text" wire:model.defer="q" placeholder="Código, DNI o Apellidos y Nombres" class="fi-input w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
				</div>
				<button type="button" wire:click="buscar" class="px-4 py-2 rounded-lg bg-primary-600 text-white hover:bg-primary-700">Buscar</button>
			</div>
		</div>

		<div class="overflow-x-auto">
			<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
				<thead>
					<tr class="bg-gray-50 dark:bg-gray-900/50">
						<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
						<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">DNI</th>
						<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Apellidos y Nombres</th>
						<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Local</th>
						<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cargo</th>
						<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fecha y hora asignación</th>
						<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">N° Planilla</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-200 dark:divide-gray-700">
					@forelse($this->resultados as $r)
						<tr>
							<td class="px-3 py-2 text-sm text-gray-800 dark:text-gray-100">{{ $r['codigo'] }}</td>
							<td class="px-3 py-2 text-sm text-gray-800 dark:text-gray-100">{{ $r['dni'] }}</td>
							<td class="px-3 py-2 text-sm text-gray-800 dark:text-gray-100">{{ $r['nombres'] }}</td>
							<td class="px-3 py-2 text-sm text-gray-800 dark:text-gray-100">{{ $r['local'] }}</td>
							<td class="px-3 py-2 text-sm text-gray-800 dark:text-gray-100">{{ $r['cargo'] }}</td>
							<td class="px-3 py-2 text-sm text-gray-800 dark:text-gray-100">{{ $r['fecha_asignacion'] ? \Carbon\Carbon::parse($r['fecha_asignacion'])->format('d/m/Y H:i') : '' }}</td>
							<td class="px-3 py-2 text-sm text-gray-800 dark:text-gray-100">{{ $r['numero_planilla'] }}</td>
						</tr>
					@empty
						<tr>
							<td colspan="7" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Sin resultados</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</div>
</x-filament-panels::page>
