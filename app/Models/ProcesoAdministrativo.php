<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcesoAdministrativo extends Model
{
    protected $table = 'procesoadministrativo';
    protected $primaryKey = 'proadm_id';
    public $incrementing = true;

    protected $fillable = [
                
         'adm_vcDni',
        'profec_iCodigo',
        'loc_iCodigo',       
        'expadm_iCodigo',
        'proadm_dtFechaAsignacion',
        'user_id',
        'proadm_iCodigo',        
        'proadm_iCredencial',
        'proadm_dtFechaImpresion',
        'proadm_iAsignacion',
        'proadm_dtFechaDesasignacion',
         'user_idImpresion',
         'proadm_vcIpImpresion',
         'proadm_vcIpAsignacion',
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

   public function administrativo()
    {
    return $this->belongsTo(Administrativo::class, 'adm_vcDni', 'adm_vcDni');
    }   

    // Una asignación pertenece a un Administrativo
    public function procesoFecha(): BelongsTo
    {
        // Foreign key en procesoadministrativo: profec_iCodigo -> clave primaria en procesofecha: profec_iCodigo
        return $this->belongsTo(ProcesoFecha::class, 'profec_iCodigo', 'profec_iCodigo');
    }

    public function getLocalCargoAttribute()
{
    return \App\Models\LocalCargo::where('loc_iCodigo', $this->loc_iCodigo ?? 0)
        ->where('loc_iCodigo', $this->loc_iCodigo ?? 0)
       
        ->first();
}

    public function usuario()
    {
    return $this->belongsTo(\App\Models\User::class, 'user_id');
    }


}
