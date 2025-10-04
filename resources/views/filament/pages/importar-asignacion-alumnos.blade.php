@php /** @var \App\Filament\Pages\ImportarAsignacionAlumnos $this */ @endphp
<x-filament::page>
	<div class="space-y-6">
		<form wire:submit.prevent="parseFile" class="p-4 bg-white dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700">
			{{ $this->form }}
			<div class="mt-4 flex gap-3">
				<x-filament::button type="submit" color="primary">Procesar / Validar</x-filament::button>
				@if(!empty($preview))
					<x-filament::button wire:click="import" color="success">Confirmar Importación</x-filament::button>
					<x-filament::button wire:click="downloadErrores" color="warning" :disabled="collect($preview)->every(fn($r)=>$r['valid'])">Descargar Errores</x-filament::button>
					<x-filament::button wire:click="downloadErroresXlsx" color="warning" :disabled="collect($preview)->every(fn($r)=>$r['valid'])">Errores XLSX</x-filament::button>
				@endif
				<x-filament::button wire:click="downloadPlantilla" color="gray" type="button">Descargar Plantilla</x-filament::button>
			</div>
		</form>

		@if(!empty($preview))
			@php
				$total = count($preview);
				$errores = collect($preview)->reject(fn($r)=>$r['valid'])->count();
				$validas = $total - $errores;
			@endphp
			<div x-data="importAsignacionAlumnos(@js($preview))" class="space-y-4">
				<div class="flex gap-4 text-sm">
					<span class="px-2 py-1 bg-green-100 text-green-800 rounded">Válidas: {{ $validas }}</span>
					<span class="px-2 py-1 bg-red-100 text-red-800 rounded">Con errores: {{ $errores }}</span>
					<span class="px-2 py-1 bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200 rounded">Total: {{ $total }}</span>
				</div>
				<div class="flex flex-wrap items-end gap-4 text-sm mt-3 mb-2">
					<div>
						<label class="block text-xs font-medium mb-1">Buscar</label>
						<input x-model.debounce.300ms="q" type="text" class="border rounded px-2 py-1 text-xs bg-white dark:bg-gray-900" placeholder="Código / DNI / Apellidos / Cargo / Local">
					</div>
					<label class="inline-flex items-center gap-2 text-xs">
						<input type="checkbox" x-model="onlyErrors"> <span>Solo errores</span>
					</label>
					<label class="inline-flex items-center gap-2 text-xs">
						<input type="checkbox" x-model="onlyWarnings"> <span>Solo warnings</span>
					</label>
					<div class="flex items-center gap-2 text-xs">
						<label>Por página</label>
						<select x-model.number="perPage" class="border rounded px-1 py-0.5 bg-white dark:bg-gray-900 text-xs">
							<option value="10">10</option>
							<option value="25">25</option>
							<option value="50">50</option>
							<option value="100">100</option>
						</select>
					</div>
					<span class="text-[10px] text-gray-500" x-text="'Filtradas: '+filtered().length+' | Página '+page+'/'+totalPages()"></span>
				</div>
				<div class="overflow-auto border rounded">
					<table class="min-w-full text-xs">
						<thead class="bg-gray-50 dark:bg-gray-700">
						<tr class="text-left">
							<th class="px-2 py-1">Fila</th>
							<th class="px-2 py-1">Código</th>
							<th class="px-2 py-1">DNI</th>
							<th class="px-2 py-1">Nombres</th>
							<th class="px-2 py-1">Cargo</th>
							<th class="px-2 py-1">Local</th>
							<th class="px-2 py-1">Fecha</th>
							<th class="px-2 py-1 w-64">Errores</th>
							<th class="px-2 py-1 w-64">Warnings</th>
							<th class="px-2 py-1">Estado</th>
						</tr>
						</thead>
						<tbody>
							<template x-for="r in paged()" :key="r.row">
							<tr :class="{'bg-red-50 dark:bg-red-900/30': !r.valid, 'bg-amber-50 dark:bg-amber-900/30': r.valid && r.warnings && r.warnings.length}" class="border-t border-gray-200 dark:border-gray-600">
								<td class="px-2 py-1" x-text="r.row"></td>
								<td class="px-2 py-1 font-mono" x-text="r.codigo"></td>
								<td class="px-2 py-1 font-mono" x-text="r.dni"></td>
								<td class="px-2 py-1" x-text="r.nombres"></td>
								<td class="px-2 py-1" x-text="r.cargo"></td>
								<td class="px-2 py-1" x-text="r.local"></td>
								<td class="px-2 py-1" x-text="r.fecha"></td>
								<td class="px-2 py-1 text-[11px] whitespace-pre-wrap" x-text="!r.valid && r.errores ? r.errores.join(' | ') : ''"></td>
								<td class="px-2 py-1 text-[11px] whitespace-pre-wrap" x-text="r.valid && r.warnings ? r.warnings.join(' | ') : ''"></td>
								<td class="px-2 py-1">
									<span x-show="r.valid" class="px-2 py-0.5 rounded bg-green-100 text-green-700 dark:bg-green-800 dark:text-green-200">OK</span>
									<span x-show="!r.valid" class="px-2 py-0.5 rounded bg-red-100 text-red-700 dark:bg-red-800 dark:text-red-200">Error</span>
								</td>
							</tr>
							</template>
							<tr x-show="filtered().length===0">
								<td colspan="10" class="px-2 py-4 text-center text-sm text-gray-500">Sin resultados (ajuste filtros o búsqueda)</td>
							</tr>
						</tbody>
					</table>
				</div>
				<div class="flex items-center justify-between gap-4 text-xs mt-2" x-show="filtered().length>0">
					<div class="flex items-center gap-1">
						<button type="button" class="px-2 py-1 border rounded" :disabled="page===1" @click="page=1">«</button>
						<button type="button" class="px-2 py-1 border rounded" :disabled="page===1" @click="prev()">‹</button>
						<span class="px-2">Página <span x-text="page"></span>/<span x-text="totalPages()"></span></span>
						<button type="button" class="px-2 py-1 border rounded" :disabled="page===totalPages()" @click="next()">›</button>
						<button type="button" class="px-2 py-1 border rounded" :disabled="page===totalPages()" @click="page=totalPages()">»</button>
					</div>
					<div class="text-[10px] text-gray-500" x-text="'Mostrando '+(((page-1)*perPage)+1)+'-'+Math.min(page*perPage, filtered().length)+' de '+filtered().length"></div>
				</div>
			</div>
		@endif

		<script>
			function importAsignacionAlumnos(rows){
				return {
					q:'',
					onlyErrors:false,
					onlyWarnings:false,
					rows: rows || [],
					page:1,
					perPage:25,
					filtered(){
						let data = this.rows;
						const qUp = this.q.trim().toUpperCase();
						if(qUp){
							data = data.filter(r => [r.codigo, r.dni, r.nombres, r.cargo, r.local, r.paterno, r.materno].some(v => (v||'').toString().toUpperCase().includes(qUp)));
						}
						if(this.onlyErrors){
							data = data.filter(r => r.errores && r.errores.length > 0);
						}
						if(this.onlyWarnings){
							data = data.filter(r => (!r.errores || r.errores.length === 0) && r.warnings && r.warnings.length > 0);
						}
						const totalP = Math.max(1, Math.ceil(data.length / this.perPage));
						if(this.page > totalP) this.page = totalP;
						return data;
					},
					paged(){
						const start = (this.page - 1) * this.perPage;
						return this.filtered().slice(start, start + this.perPage);
					},
					totalPages(){
						return Math.max(1, Math.ceil(this.filtered().length / this.perPage));
					},
					next(){ if(this.page < this.totalPages()) this.page++; },
					prev(){ if(this.page > 1) this.page--; }
				}
			}
		</script>
	</div>
</x-filament::page>
