<?php

namespace App\Livewire;

use App\Models\Proceso;
use App\Models\ProcesoFecha;
use App\Support\CurrentContext;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;
use Filament\Notifications\Notification;

class ContextSwitcher extends Component
{
    public ?int $proceso_id = null;
    public ?int $proceso_fecha_id = null;

    public array $procesoOptions = [];
    public array $fechaOptions = [];
    public int $fechaSelectVersion = 0; // fuerza re-render del select de fechas

    public function mount(CurrentContext $ctx): void
    {
        $ctx->ensureLoaded();
        $this->proceso_id = $ctx->procesoId();
        $this->proceso_fecha_id = $ctx->fechaId();
        $this->loadOptions();
        // Si el proceso/fecha actuales no son válidos (cerrados o inexistentes), limpiarlos
        if (!array_key_exists($this->proceso_id, $this->procesoOptions)) {
            $this->proceso_id = null;
            $this->proceso_fecha_id = null;
            $this->fechaOptions = [];
        } else {
            // Validar fecha vigente en opciones activas
            if (!array_key_exists($this->proceso_fecha_id, $this->fechaOptions)) {
                $this->proceso_fecha_id = null;
            }
        }
    }

    /**
     * Cambio explícito de proceso (invocado desde el select con wire:change)
     */
    public function changeProceso($value): void
    {
        // Normalizar valor: string vacía -> null
        $this->proceso_id = ($value === '' || $value === null) ? null : (int) $value;
        // Reset fecha / opciones
        $this->proceso_fecha_id = null;
        $this->fechaOptions = [];
        if ($this->proceso_id) {
            $this->loadFechaOptions();
            // No auto-seleccionar: mostrar placeholder "Seleccione fecha"
        }
        $this->fechaSelectVersion++; // fuerza re-render
    }

    /**
     * Cambio explícito de fecha (invocado desde el select con wire:change)
     */
    public function changeFecha($value): void
    {
        $this->proceso_fecha_id = ($value === '' || $value === null) ? null : (int) $value;
    }

    public function apply(CurrentContext $ctx): void
    {
        // Validaciones: no permitir aplicar si no hay proceso abierto o fecha activa
        if (!$this->proceso_id || empty($this->procesoOptions)) {
            Notification::make()->title('Sin procesos abiertos')->body('No hay proceso abierto para seleccionar.')->warning()->send();
            return;
        }
        if (!$this->proceso_fecha_id || empty($this->fechaOptions)) {
            Notification::make()->title('Sin fechas activas')->body('No hay fechas activas disponibles para el proceso seleccionado.')->warning()->send();
            return;
        }
        if (!array_key_exists($this->proceso_id, $this->procesoOptions) || !array_key_exists($this->proceso_fecha_id, $this->fechaOptions)) {
            Notification::make()->title('Selección inválida')->body('Seleccione un proceso y una fecha válidos.')->warning()->send();
            return;
        }
        $ctx->set($this->proceso_id, $this->proceso_fecha_id);
        $this->dispatch('context-changed');
        session()->flash('context_updated', true);
        // Notificación de confirmación
        $procesoLabel = $this->procesoOptions[$this->proceso_id] ?? ('Proceso '.$this->proceso_id);
        $fechaLabel = $this->fechaOptions[$this->proceso_fecha_id] ?? ('Fecha '.$this->proceso_fecha_id);
        Notification::make()
            ->title('Contexto actualizado')
            ->body($procesoLabel.' / '.$fechaLabel)
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.context-switcher');
    }

    protected function loadOptions(): void
    {
        $this->procesoOptions = Proceso::query()
            ->where('pro_iAbierto', true)
            ->orderBy('pro_vcNombre')
            ->pluck('pro_vcNombre', 'pro_iCodigo')
            ->toArray();
        // Si el proceso seleccionado ya no está abierto, limpiar selección
        if (!array_key_exists($this->proceso_id, $this->procesoOptions)) {
            $this->proceso_id = null;
        }
        $this->loadFechaOptions();
    }

    protected function loadFechaOptions(): void
    {
        $this->fechaOptions = [];
        if (!$this->proceso_id) return;
        $rows = ProcesoFecha::query()
            ->where('pro_iCodigo', $this->proceso_id)
            ->where('profec_iActivo', true)
            ->orderBy('profec_dFecha')
            ->get();
        $this->fechaOptions = $rows->mapWithKeys(function ($f) {
            $dateValue = $f->profec_dFecha;
            $labelDate = '';
            if ($dateValue instanceof \DateTimeInterface) {
                $labelDate = $dateValue->format('d/m/Y');
            } elseif (is_string($dateValue) && $dateValue !== '') {
                try { $labelDate = Carbon::parse($dateValue)->format('d/m/Y'); } catch (\Throwable $e) { $labelDate = (string) $f->profec_iCodigo; }
            } else {
                $labelDate = (string) $f->profec_iCodigo;
            }
            $label = $labelDate;
            return [ $f->profec_iCodigo => $label ];
        })->toArray();
        // Si la fecha seleccionada ya no está en opciones activas, limpiar
        if (!array_key_exists($this->proceso_fecha_id, $this->fechaOptions)) {
            $this->proceso_fecha_id = null;
        }
    }
}
