<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Relations\Pivot;


class LocalCargo extends Pivot
{
    protected $table = 'localcargo';
    protected $primaryKey = 'loccar_iCodigo';

    public $incrementing = true;
    protected $keyType = 'int'; 

    protected $fillable = [
        'loc_iCodigo',
        'expadm_iCodigo',
        'loccar_iVacante',
        'loccar_iOcupado',
    ];


   
    public function localesMaestro()
    {
        return $this->belongsTo(LocalesMaestro::class, 'loc_iCodigo', 'locma_iCodigo');
    }

  
    public function maestro()
    {
        return $this->belongsTo(ExperienciaAdmisionMaestro::class, 'expadm_iCodigo', 'expadmma_iCodigo');
    }
}
