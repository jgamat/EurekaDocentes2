@php /** @var \App\Filament\Pages\ImportarCargosActualizar $this */ @endphp
<x-filament::page>
	<div class="space-y-6">
		<form wire:submit.prevent="parseFile" class="p-4 bg-white dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 space-y-4">
			{{ $this->form }}
			<div class="flex gap-3">
				<x-filament::button type="submit" color="primary">Procesar / Validar</x-filament::button>
				@if(!empty($preview))
					<x-filament::button wire:click="apply" color="success" :disabled="$onlyValidate || collect($preview)->where('estado','ok_cambiar')->isEmpty()">Aplicar Cambios</x-filament::button>
					<x-filament::button wire:click="downloadErrores" color="warning" :disabled="collect($preview)->where('estado','error')->isEmpty()">Errores CSV</x-filament::button>
				@endif
			</div>
		</form>

		@if(!empty($preview))
			@php
				$total = $stats['total'] ?? count($preview);
				$cambiar = $stats['cambiar'] ?? collect($preview)->where('estado','ok_cambiar')->count();
				$sinCambio = $stats['sin_cambio'] ?? collect($preview)->where('estado','sin_cambio')->count();
				$errores = $stats['errores'] ?? collect($preview)->where('estado','error')->count();
				$duplicados = $stats['duplicados'] ?? collect($preview)->where('estado','duplicado')->count();
			@endphp
			<div x-data="importarCargosActualizar(@js($preview), @js($showOnlyChanges))" class="space-y-4">
				<div class="flex flex-wrap gap-2 text-xs">
					<span class="px-2 py-1 rounded bg-blue-100 text-blue-800">Total: {{ $total }}</span>
					<span class="px-2 py-1 rounded bg-green-100 text-green-800">Con cambio: {{ $cambiar }}</span>
					<span class="px-2 py-1 rounded bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">Sin cambio: {{ $sinCambio }}</span>
					<span class="px-2 py-1 rounded bg-red-100 text-red-700">Errores: {{ $errores }}</span>
					<span class="px-2 py-1 rounded bg-amber-100 text-amber-700">Duplicados: {{ $duplicados }}</span>
				</div>
				<div class="flex flex-wrap items-end gap-4 text-xs">
					<div>
						<label class="block text-[11px] font-medium mb-1">Buscar</label>
						<input x-model.debounce.300ms="q" type="text" class="border rounded px-2 py-1 text-xs bg-white dark:bg-gray-900" placeholder="Código / Nombre / Monto">
					</div>
					<label class="inline-flex items-center gap-1">
						<input type="checkbox" x-model="onlyErrors"> <span>Solo errores</span>
					</label>
					<label class="inline-flex items-center gap-1">
						<input type="checkbox" x-model="onlyChanges"> <span>Solo con cambio</span>
					</label>
					<label class="inline-flex items-center gap-1">
						<input type="checkbox" x-model="onlyWarnings"> <span>Solo warnings</span>
					</label>
					<div class="flex items-center gap-2">
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
								<th class="px-2 py-1">Nombre BD</th>
								<th class="px-2 py-1">Nombre Excel</th>
								<th class="px-2 py-1">Monto Actual</th>
								<th class="px-2 py-1">Monto Nuevo</th>
								<th class="px-2 py-1">Dif</th>
								<th class="px-2 py-1 w-56">Errores</th>
								<th class="px-2 py-1 w-56">Warnings</th>
								<th class="px-2 py-1">Estado</th>
							</tr>
						</thead>
						<tbody>
							<template x-for="r in paged()" :key="r.row">
								<tr :class="rowClass(r)" class="border-t border-gray-200 dark:border-gray-600">
									<td class="px-2 py-1" x-text="r.row"></td>
									<td class="px-2 py-1 font-mono" x-text="r.codigo"></td>
									<td class="px-2 py-1" x-text="r.nombre_bd"></td>
									<td class="px-2 py-1" x-text="r.nombre_excel"></td>
									<td class="px-2 py-1 text-right" x-text="formatMonto(r.monto_actual)"></td>
									<td class="px-2 py-1 text-right" x-text="formatMonto(r.monto_nuevo)"></td>
									<td class="px-2 py-1 text-right" x-html="diffBadge(r)"></td>
									<td class="px-2 py-1 text-[11px] whitespace-pre-wrap" x-text="r.estado==='error' ? r.errores.join(' | ') : ''"></td>
									<td class="px-2 py-1 text-[11px] whitespace-pre-wrap" x-text="r.estado!=='error' && r.warnings ? r.warnings.join(' | ') : ''"></td>
									<td class="px-2 py-1">
										<template x-if="r.estado==='ok_cambiar'"><span class="px-2 py-0.5 rounded bg-blue-100 text-blue-800">Actualizar</span></template>
										<template x-if="r.estado==='sin_cambio'"><span class="px-2 py-0.5 rounded bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200">Igual</span></template>
										<template x-if="r.estado==='error'"><span class="px-2 py-0.5 rounded bg-red-100 text-red-700">Error</span></template>
										<template x-if="r.estado==='duplicado'"><span class="px-2 py-0.5 rounded bg-amber-100 text-amber-800">Duplicado</span></template>
									</td>
								</tr>
							</template>
							<tr x-show="filtered().length===0">
								<td colspan="10" class="px-2 py-4 text-center text-sm text-gray-500">Sin resultados</td>
							</tr>
						</tbody>
					</table>
				</div>
				<div class="flex items-center justify-between gap-4 text-xs" x-show="filtered().length>0">
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
	</div>

	<script>
		function importarCargosActualizar(rows, showOnlyChanges){
			return {
				q:'',
				onlyErrors:false,
				onlyChanges: showOnlyChanges || false,
				onlyWarnings:false,
				rows: rows || [],
				page:1,
				perPage:25,
				filtered(){
					let data = this.rows;
					const qUp = this.q.trim().toUpperCase();
					if(qUp){
						data = data.filter(r => [r.codigo, r.nombre_bd, r.nombre_excel, r.monto_actual, r.monto_nuevo].some(v => (v??'').toString().toUpperCase().includes(qUp)) );
					}
					if(this.onlyErrors){ data = data.filter(r => r.estado==='error'); }
					if(this.onlyChanges){ data = data.filter(r => r.estado==='ok_cambiar'); }
					if(this.onlyWarnings){ data = data.filter(r => r.estado!=='error' && r.warnings && r.warnings.length>0); }
					const totalP = Math.max(1, Math.ceil(data.length / this.perPage));
					if(this.page > totalP) this.page = totalP;
					return data;
				},
				paged(){
					const start = (this.page - 1) * this.perPage;
					return this.filtered().slice(start, start + this.perPage);
				},
				totalPages(){ return Math.max(1, Math.ceil(this.filtered().length / this.perPage)); },
				next(){ if(this.page < this.totalPages()) this.page++; },
				prev(){ if(this.page > 1) this.page--; },
				formatMonto(v){ if(v===null||v===undefined||v==='') return ''; return parseFloat(v).toFixed(2); },
				diffBadge(r){
					if(r.monto_actual===null || r.monto_nuevo===null) return '';
					const diff = parseFloat(r.monto_nuevo) - parseFloat(r.monto_actual);
					if(Math.abs(diff) < 0.00001) return '<span class="text-gray-400">0.00</span>';
					const cls = diff>0 ? 'text-green-600' : 'text-red-600';
					return `<span class="font-mono ${cls}">${diff>0?'+':''}${diff.toFixed(2)}</span>`;
				},
				rowClass(r){
					if(r.estado==='error') return 'bg-red-50 dark:bg-red-900/30';
					if(r.estado==='duplicado') return 'bg-amber-50 dark:bg-amber-900/30';
					if(r.estado==='ok_cambiar') return 'bg-blue-50 dark:bg-blue-900/30';
					return '';
				}
			}
		}
	</script>
</x-filament::page>
