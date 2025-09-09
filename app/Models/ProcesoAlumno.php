<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcesoAlumno extends Model
{
    protected $table = 'procesoalumno';
    protected $primaryKey = 'proalu_id';
    public $incrementing = true;

     protected $fillable = [
                
         'alu_vcCodigo',
        'profec_iCodigo',
        'loc_iCodigo',       
        'expadm_iCodigo',
        'proalu_dtFechaAsignacion',
        'user_id',
        'proalu_iCodigo',        
        'proalu_iCredencial',
        'proalu_dtFechaImpresion',
        'proalu_iAsignacion',
        'proalu_dtFechaDesasignacion',
        'proalu_vcIpAsignacion',

    ];

     // Una asignación pertenece a un Local
    public function local(): BelongsTo
    {
        return $this->belongsTo(Locales::class, 'loc_iCodigo');
    }

    // Una asignación pertenece a una ExperienciaAdmision (cargo)
    public function experienciaAdmision(): BelongsTo
    {
        return $this->belongsTo(ExperienciaAdmision::class, 'expadm_iCodigo');
    }

   public function alumno()
    {
    return $this->belongsTo(Alumno::class, 'alu_vcCodigo', 'alu_vcCodigo');
    }

   
    public function procesoFecha(): BelongsTo
    {
        return $this->belongsTo(ProcesoFecha::class, 'profec_iCodigo');
    }

    public function getLocalCargoAttribute()
    {
        $locId = $this->loc_iCodigo ?? null;
        $cargoId = $this->expadm_iCodigo ?? null;
        if (!$locId || !$cargoId) {
            return null;
        }
        return \App\Models\LocalCargo::where('loc_iCodigo', $locId)
            ->where('expadm_iCodigo', $cargoId)
            ->first();
    }

    public function usuario()
    {
    return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

}
