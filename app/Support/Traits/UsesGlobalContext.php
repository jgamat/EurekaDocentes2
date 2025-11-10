<?php

namespace App\Support\Traits;

use App\Models\ProcesoFecha;
use App\Support\CurrentContext;
use Carbon\Carbon;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;

trait UsesGlobalContext
{
    /**
     * Devuelve un Placeholder de solo lectura que muestra la fecha actual del contexto
     * tomando el valor desde el campo indicado (por defecto 'proceso_fecha_id').
     */
    protected function fechaActualPlaceholder(string $fechaField = 'proceso_fecha_id'): Placeholder
    {
        return Placeholder::make('')
            ->label(null)
            ->content(function (callable $get) use ($fechaField) {
                $id = $get($fechaField);
                $valor = '-';
                if ($id) {
                    $f = ProcesoFecha::find($id);
                    if ($f) {
                        $d = $f->profec_dFecha;
                        if ($d instanceof \DateTimeInterface) {
                            $valor = $d->format('d/m/Y');
                        } elseif (is_string($d) && $d !== '') {
                            try { $valor = Carbon::parse($d)->format('d/m/Y'); } catch (\Throwable $e) { $valor = (string) $id; }
                        } else {
                            $valor = (string) $id;
                        }
                    } else {
                        $valor = (string) $id;
                    }
                }
                return 'Fecha Actual: ' . $valor;
            });
    }

    /**
     * Rellena el formulario con valores del contexto global.
     * Campos soportados: 'proceso_id', 'proceso_fecha_id'.
     */
    protected function fillContextDefaults(array $fields = ['proceso_id', 'proceso_fecha_id']): void
    {
        $ctx = app(CurrentContext::class);
        $payload = [];
        foreach ($fields as $field) {
            if ($field === 'proceso_id') {
                $payload[$field] = $ctx->procesoId();
            } elseif ($field === 'proceso_fecha_id') {
                $payload[$field] = $ctx->fechaId();
            }
        }
        if (!empty($payload) && method_exists($this, 'form')) {
            $this->form->fill($payload);
        }
    }

    /**
     * Aplica el contexto global al formulario, reinicia campos y notifica opcionalmente.
     * - $fields: lista de campos a sincronizar desde CurrentContext (por defecto ambos)
     * - $resetFields: lista de claves de state a reiniciar a null
     */
    protected function applyContextFromGlobal(
        array $fields = ['proceso_id', 'proceso_fecha_id'],
        array $resetFields = [],
        ?string $notificationBody = null
    ): void {
        $ctx = app(CurrentContext::class);
        $payload = [];
        foreach ($fields as $field) {
            if ($field === 'proceso_id') {
                $payload[$field] = $ctx->procesoId();
            } elseif ($field === 'proceso_fecha_id') {
                $payload[$field] = $ctx->fechaId();
            }
        }
        foreach ($resetFields as $k) {
            $payload[$k] = null;
        }
        if (!empty($payload) && method_exists($this, 'form')) {
            $this->form->fill($payload);
        }
        if ($notificationBody) {
            Notification::make()->title('Contexto actualizado')->body($notificationBody)->info()->send();
        }
    }
}
