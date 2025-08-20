<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Locales extends Model
{
    
    protected $table = 'locales';
    protected $primaryKey = 'loc_iCodigo';

    public $incrementing = true;
    protected $keyType = 'int';

    

    public function procesoFecha(): BelongsTo
    {
        // Foreign key en locales: profec_iCodigo -> clave primaria en procesofecha: profec_iCodigo
        return $this->belongsTo(ProcesoFecha::class, 'profec_iCodigo', 'profec_iCodigo');
    }

    public function localesMaestro()
    {
    return $this->belongsTo(LocalesMaestro::class, 'locma_iCodigo', 'locma_iCodigo');
    }

   public function experienciaAdmision(): BelongsToMany
    {
        return $this->belongsToMany(
            ExperienciaAdmision::class,
            'localcargo',         // 1. Nombre de la tabla pivote
            'loc_iCodigo',        // 2. Clave foránea de ESTE modelo (Locales)
            'expadm_iCodigo'      // 3. Clave foránea del OTRO modelo (ExperienciaAdminision)
        )
        
        ->withPivot('loccar_iVacante', 'loccar_iOcupado')
        ->withTimestamps();
        
    }

   
    public function asignacionesDocentes(): HasMany
    {
        return $this->hasMany(ProcesoDocente::class, 'loc_iCodigo');
    }

    public function asignacionesAdministrativos(): HasMany
    {
        return $this->hasMany(ProcesoAdministrativo::class, 'loc_iCodigo');
    }

    public function asignacionesAlumnos(): HasMany
    {
        return $this->hasMany(ProcesoAlumno::class, 'loc_iCodigo');
    }

    
}

