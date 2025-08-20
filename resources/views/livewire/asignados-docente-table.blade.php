<div>
    {{-- Bloque de Depuración para el Componente de la Tabla --}}

    @if($procesoFechaId && $localId && $experienciaAdmisionId)
        <div class="mt-12 border-t pt-8">
            {{ $this->table }}
        </div>
    @endif
</div>