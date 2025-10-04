<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CargoMontoHistorial extends Model
{
    protected $table = 'cargo_monto_historial';
    protected $fillable = [
        'expadm_iCodigo',
        'monto_anterior',
        'monto_nuevo',
        'user_id',
        'archivo_original',
        'fuente',
        'aplicado_en',
    ];

    protected $casts = [
        'monto_anterior' => 'decimal:2',
        'monto_nuevo' => 'decimal:2',
        'aplicado_en' => 'datetime',
    ];

    /**
     * Cargo (instancia) al que pertenece el cambio.
     */
    public function cargo(): BelongsTo
    {
        return $this->belongsTo(ExperienciaAdmision::class, 'expadm_iCodigo', 'expadm_iCodigo');
    }

    /**
     * Usuario que aplicó el cambio (si existe tabla users).
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Accessor calculado: diferencia (monto_nuevo - monto_anterior)
     */
    public function getDiferenciaAttribute(): ?float
    {
        if ($this->monto_nuevo === null || $this->monto_anterior === null) return null;
        return (float)$this->monto_nuevo - (float)$this->monto_anterior;
    }
}
