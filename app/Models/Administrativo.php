<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Administrativo extends Model
{
    protected $table = 'administrativo';
    protected $primaryKey = 'adm_id';

    public $incrementing = true;
    protected $keyType = 'int';
 

    protected $fillable = [
        'adm_vcTipo',
        'adm_vcCodigo',
         'adm_vcDni',
        'adm_vcNombres',
        'dep_vcCodigo',       
        'cat_vcCodigo',
        'con_iCodigo',
        'adm_vcUrlFoto',
        'adm_dNacimiento',        
        'adm_vcTelefono',
        'adm_vcCelular',
        'adm_vcEmailUNMSM',
        'adm_vcEmailPersonal',
         'tipo_iCodigo',
       
       
       
    ];

    public function dependencia()
    {
    return $this->belongsTo(Dependencia::class, 'dep_iCodigo', 'dep_iCodigo');
    }

    public function categoria()
    {
    return $this->belongsTo(Categoria::class, 'cat_vcCodigo', 'cat_vcCodigo');
    }

    public function condicion()
    {
    return $this->belongsTo(Condicion::class, 'con_iCodigo', 'con_iCodigo');
    }

    public function estado()
    {
    return $this->belongsTo(Estado::class, 'est_iCodigo', 'est_iCodigo');
    }

    public function asignaciones(): HasMany
    {
        // Foreign key en procesoadministrativo = adm_vcDni, local key en administrativo = adm_vcDni
        return $this->hasMany(ProcesoAdministrativo::class, 'adm_vcDni', 'adm_vcDni');
    }

    public function tipo()
    {
    return $this->belongsTo(Tipo::class, 'tipo_iCodigo', 'tipo_iCodigo');
    } 

   
}
