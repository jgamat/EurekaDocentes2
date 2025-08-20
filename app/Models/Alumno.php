<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alumno extends Model
{
    protected $table = 'alumno';
    protected $primaryKey = 'alu_id';

    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'alu_vcCodigo',
        'alu_vcDni',
         'alu_vcPaterno',
        'alu_vcMaterno',
        'alu_vcNombre',       
        'alu_vcEmail',
        'alu_vcEmailPer',
        'alu_vcCelular',
        'esc_vcNombre',        
        'fac_vcNombre',
        'alu_iMatriculaUltima',
        'alu_iAnioIngreso',
        'alu_iAnioIngreso',
         'user_idImpresion',
         'proalu_vcIpImpresion',
       
    ];



    public function asignaciones(): HasMany
    {
        return $this->hasMany(ProcesoAlumno::class, 'alu_vcCodigo', 'alu_vcCodigo');
    }

    public function tipo()
    {
    return $this->belongsTo(Tipo::class, 'tipo_iCodigo', 'tipo_iCodigo');
    } 

     public function getNombreCompletoAttribute(): string
    {
    return "{$this->alu_vcPaterno} {$this->alu_vcMaterno} {$this->alu_vcNombre}";
    }


}
