<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExperienciaAdmisionMaestro extends Model
{
    protected $table = 'experienciaadmisionMaestro';
    protected $primaryKey = 'expadmma_iCodigo';
    public $incrementing = true;
    protected $fillable = ['expadmma_vcNombre'];

    
    public function instancias(): HasMany
    {
        return $this->hasMany(ExperienciaAdmision::class, 'expadmma_iCodigo');
    }
}
