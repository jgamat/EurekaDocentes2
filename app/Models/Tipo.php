<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tipo extends Model
{
     protected $table = 'tipo';
    protected $primaryKey = 'tipo_iCodigo';

    public $incrementing = true;

    protected $fillable = [
        'tipo_vcNombrePlanilla',
        
    ];
}
