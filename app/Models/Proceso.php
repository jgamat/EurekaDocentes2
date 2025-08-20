<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proceso extends Model
{
    protected $table = 'proceso';
    protected $primaryKey = 'pro_iCodigo';
    public $incrementing = true;

    protected $fillable = [
       
        'pro_vcNombre',
        'pro_dFechaInicio',
        'pro_dFechaFin',
        'pro_iAbierto',
        'pro_iCategoria',
        'pro_iTipo'
        
    ];

     protected $casts = [
        'pro_iAbierto' => 'boolean', 
        
    ];

     public function procesoFecha(): HasMany
    {
        return $this->hasMany(ProcesoFecha::class, 'pro_iCodigo', 'pro_iCodigo');
    }
}
