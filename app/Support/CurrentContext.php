<?php

namespace App\Support;

use App\Models\Proceso;
use App\Models\ProcesoFecha;
use App\Models\UserPreference;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class CurrentContext
{
    public const SESSION_PROCESO_ID = 'context.proceso_id';
    public const SESSION_FECHA_ID = 'context.fecha_id';

    public function procesoId(): ?int
    {
        return Session::get(self::SESSION_PROCESO_ID);
    }

    public function fechaId(): ?int
    {
        return Session::get(self::SESSION_FECHA_ID);
    }

    public function set(?int $procesoId, ?int $fechaId): void
    {
        // Persist in session
        Session::put(self::SESSION_PROCESO_ID, $procesoId);
        Session::put(self::SESSION_FECHA_ID, $fechaId);

        // Persist in user preferences (if authenticated)
        if ($user = $this->user()) {
            $this->savePreference($user->getAuthIdentifier(), 'context.proceso_id', $procesoId);
            $this->savePreference($user->getAuthIdentifier(), 'context.fecha_id', $fechaId);
        }
    }

    public function clear(): void
    {
        Session::forget([self::SESSION_PROCESO_ID, self::SESSION_FECHA_ID]);
    }

    public function proceso(): ?Proceso
    {
        $id = $this->procesoId();
        if (!$id) return null;
        return Proceso::find($id);
    }

    public function fecha(): ?ProcesoFecha
    {
        $id = $this->fechaId();
        if (!$id) return null;
        return ProcesoFecha::find($id);
    }

    public function ensureLoaded(): void
    {
        // If session has no values, try to load from user preferences
        if (!$this->procesoId() || !$this->fechaId()) {
            $this->loadFromPreferences();
        }

        // If still missing, pick defaults: latest open process and its active date (or latest date)
        if (!$this->procesoId()) {
            $proc = Proceso::query()->where('pro_iAbierto', true)->orderByDesc('pro_iCodigo')->first()
                ?? Proceso::query()->orderByDesc('pro_iCodigo')->first();
            $procId = $proc?->pro_iCodigo ? (int) $proc->pro_iCodigo : null;
            if ($procId) {
                Session::put(self::SESSION_PROCESO_ID, $procId);
            }
        }

        if (!$this->fechaId() && $this->procesoId()) {
            $fecha = ProcesoFecha::query()
                ->where('pro_iCodigo', $this->procesoId())
                ->where('profec_iActivo', true)
                ->orderByDesc('profec_dFecha')
                ->first()
                ?? ProcesoFecha::query()->where('pro_iCodigo', $this->procesoId())->orderByDesc('profec_dFecha')->first();
            $fechaId = $fecha?->profec_iCodigo ? (int) $fecha->profec_iCodigo : null;
            if ($fechaId) {
                Session::put(self::SESSION_FECHA_ID, $fechaId);
            }
        }
    }

    public function ensureValid(): void
    {
        // If current values point to invalid/closed context, try to repair
        $proceso = $this->proceso();
        if ($proceso && !$proceso->pro_iAbierto) {
            // Pick another open process
            $fallback = Proceso::query()->where('pro_iAbierto', true)->orderByDesc('pro_iCodigo')->first();
            $this->set($fallback?->pro_iCodigo, null);
        }
        if ($this->procesoId() && $this->fechaId()) {
            $fecha = $this->fecha();
            if (!$fecha || $fecha->pro_iCodigo !== $this->procesoId()) {
                $this->set($this->procesoId(), null);
            }
        }
    }

    public function overrideFromQuery(?int $procesoId, ?int $fechaId): void
    {
        if ($procesoId) {
            Session::put(self::SESSION_PROCESO_ID, $procesoId);
        }
        if ($fechaId) {
            Session::put(self::SESSION_FECHA_ID, $fechaId);
        }
    }

    protected function loadFromPreferences(): void
    {
        if (!$user = $this->user()) return;
        $procPref = UserPreference::query()->where('user_id', $user->getAuthIdentifier())->where('key', 'context.proceso_id')->value('value');
        $fechaPref = UserPreference::query()->where('user_id', $user->getAuthIdentifier())->where('key', 'context.fecha_id')->value('value');
        if ($procPref) Session::put(self::SESSION_PROCESO_ID, (int) $procPref);
        if ($fechaPref) Session::put(self::SESSION_FECHA_ID, (int) $fechaPref);
    }

    protected function savePreference(int $userId, string $key, $value): void
    {
        UserPreference::updateOrCreate(
            ['user_id' => $userId, 'key' => $key],
            ['value' => $value]
        );
    }

    protected function user(): ?Authenticatable
    {
        return Auth::user();
    }
}
