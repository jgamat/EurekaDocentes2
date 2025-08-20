<div>
    @if ($procesoFechaId && $localId && $experienciaAdmisionId)
        {{ $this->table }}
    @else
        <div class="p-4 text-sm text-gray-600">Seleccione Fecha, Local y Cargo para ver los alumnos asignados.</div>
    @endif
</div>
