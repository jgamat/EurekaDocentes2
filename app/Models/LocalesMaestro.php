<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LocalesMaestro extends Model
{
    protected $table = 'localMaestro';
    protected $primaryKey = 'locma_iCodigo';
    public $incrementing = true;

    protected $fillable = [
        'locma_vcNombre'
        
    ];

    public function scopeSearch($query, $search)
    {
        return $query->where('locma_vcNombre', 'like', '%' . $search . '%');
    }

    
    public function procesoFechas(): BelongsToMany
    {
        return $this->belongsToMany(ProcesoFecha::class, 'locales', 'locma_iCodigo', 'profec_iCodigo');
    }

}
