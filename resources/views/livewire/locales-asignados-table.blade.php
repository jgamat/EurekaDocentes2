<div>
    {{-- Si hay una fecha, muestra la tabla. Si no, no muestra nada. --}}
    @if($fecha)
        {{ $this->table }}
    @endif
</div>