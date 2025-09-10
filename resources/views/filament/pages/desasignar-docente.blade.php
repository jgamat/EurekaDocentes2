<x-filament-panels::page>
    {{ $this->form }}

    @if($this->asignacionActual)
       <div class="mt-6 p-4 border rounded bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
            <h3 class="font-bold mb-2">Detalle de Asignación</h3>
            <ul>
                <li><b>Docente:</b> {{ $this->asignacionActual->docente->nombre_completo ?? '-' }}</li>
                <li><b>DNI:</b> {{ $this->asignacionActual->docente->doc_vcDni ?? '-' }}</li>
                <li><b>Código:</b> {{ $this->asignacionActual->docente->doc_vcCodigo ?? '-' }}</li>
                <li><b>Fecha:</b> {{ $this->asignacionActual->procesoFecha->profec_dFecha ?? '-' }}</li>
                <li><b>Local:</b> {{ optional($this->asignacionActual->local?->localesMaestro)->locma_vcNombre ?? '-' }}</li>
                <li><b>Cargo:</b> {{ optional($this->asignacionActual->experienciaAdmision?->maestro)->expadmma_vcNombre ?? '-' }}</li>
                <li><b>Usuario Asignador:</b> {{ $this->asignacionActual->usuario->name ?? '-' }}</li>
                <li><b>Fecha Asignación:</b> {{ $this->asignacionActual->prodoc_dtFechaAsignacion ?? '-' }}</li>
            </ul>
            <br>
           
        </div>
    @endif

</x-filament-panels::page>    