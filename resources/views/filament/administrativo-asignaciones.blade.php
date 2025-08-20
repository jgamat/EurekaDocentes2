@php
/** @var \App\Models\Administrativo $record */
/** @var \Illuminate\Support\Collection|\App\Models\ProcesoAdministrativo[] $asignaciones */
@endphp
<div class="space-y-3">
    <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Asignaciones</h3>
        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $asignaciones->count() }} registro(s)</span>
    </div>
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
        <div class="overflow-x-auto max-w-full px-2 scrollbar-thin scrollbar-thumb-gray-300 dark:scrollbar-thumb-gray-600">
            <table class="min-w-[1100px] text-xs">
                <thead class="bg-gray-100 dark:bg-gray-800/90">
                    <tr class="text-gray-600 dark:text-gray-300">
                        <th class="px-3 py-2 text-left font-medium">Proceso</th>
                        <th class="px-3 py-2 text-left font-medium">Fecha</th>
                        <th class="px-3 py-2 text-left font-medium">Local</th>
                        <th class="px-3 py-2 text-left font-medium">Cargo</th>
                        <th class="px-3 py-2 text-left font-medium">Usuario</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($asignaciones as $asig)
                    @php
                        $pf = $asig->procesoFecha;
                        $proceso = $pf?->proceso;
                        $local = $asig->local?->localesMaestro;
                        $cargo = $asig->experienciaAdmision;
                        $cargoNombre = $cargo?->maestro?->expadmma_vcNombre ?? $cargo?->expadm_vcNombre;
                        $fecha = optional($pf)->profec_dFecha;
                    @endphp
                    <tr class="transition-colors text-gray-700 dark:text-gray-200 hover:bg-gray-100/60 dark:hover:bg-gray-700/60">
                        <td class="px-3 py-2">
                            <div class="font-medium text-gray-800 dark:text-gray-100">{{ $proceso->pro_vcNombre ?? '—' }}</div>
                            @if($pf)
                                <div class="text-[10px] text-gray-400 dark:text-gray-500">Fecha ID: {{ $pf->profec_iCodigo }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap font-mono text-[11px]">{{ $fecha }}</td>
                        <td class="px-3 py-2">{{ $local->locma_vcNombre ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $cargoNombre ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $asig->usuario?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-8 text-center text-gray-500 dark:text-gray-400">Sin asignaciones registradas.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="text-[10px] text-right text-gray-400 dark:text-gray-500">Actualizado: {{ now()->format('d/m/Y H:i:s') }}</div>
</div>
