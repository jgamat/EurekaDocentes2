<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExperienciaAdmision extends Model
{
    protected $table = 'experienciaadmision';
    protected $primaryKey = 'expadm_iCodigo';

    public $incrementing = true;

    protected $fillable = [
        'expadmma_iCodigo', // FK al maestro
        'profec_iCodigo',   // FK a proceso fecha
        // 'expadm_vcNombre', // (No existe en la tabla según error reportado; comentar hasta validar esquema real)
        'expadm_fMonto'
    ];

    protected $casts = [
        'expadm_fMonto' => 'decimal:2',
    ];

    public function locales(): BelongsToMany
    {
        return $this->belongsToMany(
            Locales::class,
            'localcargo',
            'expadm_iCodigo',
            'loc_iCodigo'
        )
        ->withPivot('loccar_iVacante', 'loccar_iOcupado')
        ->withTimestamps();
    }

    // Una instancia de cargo pertenece a UNA fecha de proceso.
    public function procesoFecha(): BelongsTo
    {
        // Foreign key en experienciaadmision: profec_iCodigo -> clave primaria en procesofecha: profec_iCodigo
        return $this->belongsTo(ProcesoFecha::class, 'profec_iCodigo', 'profec_iCodigo');
    }

    public function maestro()
    {
    return $this->belongsTo(ExperienciaAdmisionMaestro::class, 'expadmma_iCodigo', 'expadmma_iCodigo');
    }

    public function asignacionesDocentes(): HasMany
    {
        return $this->hasMany(ProcesoDocente::class, 'expadm_iCodigo');
    }

     public function asignacionesAlumnos(): HasMany
    {
        return $this->hasMany(ProcesoAlumno::class, 'expadm_iCodigo');
    }

    public function asignacionesAdministrativos(): HasMany
    {
        return $this->hasMany(ProcesoAdministrativo::class, 'expadm_iCodigo');
    }

    // Accessor para exponer nombre del cargo desde el maestro
    public function getNombreAttribute(): ?string
    {
        // Evita N+1 si ya está cargada la relación maestro
        if ($this->relationLoaded('maestro')) {
            return optional($this->maestro)->expadmma_vcNombre;
        }
        return $this->maestro?->expadmma_vcNombre;
    }
    
}
