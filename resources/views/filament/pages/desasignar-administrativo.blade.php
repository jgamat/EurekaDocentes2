<x-filament-panels::page>
	{{ $this->form }}

	@if($this->asignacionActual)
	   <div class="mt-6 p-4 border rounded bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
			<h3 class="font-bold mb-2">Detalle de Asignación</h3>
			<ul>
				<li><b>Administrativo:</b> {{ $this->asignacionActual->administrativo->adm_vcNombres ?? '-' }}</li>
				<li><b>DNI:</b> {{ $this->asignacionActual->administrativo->adm_vcDni ?? '-' }}</li>
				<li><b>Código:</b> {{ $this->asignacionActual->administrativo->adm_vcCodigo ?? '-' }}</li>
				<li><b>Fecha:</b> {{ $this->asignacionActual->procesoFecha->profec_dFecha ?? '-' }}</li>
				<li><b>Usuario Asignador:</b> {{ $this->asignacionActual->usuario->name ?? '-' }}</li>
				<li><b>Fecha Asignación:</b> {{ $this->asignacionActual->proadm_dtFechaAsignacion ?? '-' }}</li>
			</ul>
			<br>
           
		</div>
	@endif

</x-filament-panels::page>
