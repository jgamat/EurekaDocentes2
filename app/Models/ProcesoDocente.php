<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcesoDocente extends Model
{
    protected $table = 'procesodocente';
    protected $primaryKey = 'prodoc_id';
    public $incrementing = true; 

    protected $fillable = [
                
         'doc_vcCodigo',
        'profec_iCodigo',
        'loc_iCodigo',       
        'expadm_iCodigo',
        'prodoc_dtFechaAsignacion',
        'user_id',
        'prodoc_iCodigo',        
        'prodoc_iCredencial',
        'prodoc_dtFechaImpresion',
        'prodoc_iAsignacion',
        'prodoc_dtFechaDesasignacion',
         'user_idImpresion',
         'prodoc_vcIpImpresion',
         'prodoc_vcIpAsignacion',
         'user_idDesasignador',
       
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

   public function docente()
    {
    return $this->belongsTo(Docente::class, 'doc_vcCodigo', 'doc_vcCodigo');
    }   

    // Una asignación pertenece a un Docente
    public function procesoFecha(): BelongsTo
    {
        // Foreign key en procesodocente: profec_iCodigo -> clave primaria en procesofecha: profec_iCodigo
        return $this->belongsTo(ProcesoFecha::class, 'profec_iCodigo', 'profec_iCodigo');
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
