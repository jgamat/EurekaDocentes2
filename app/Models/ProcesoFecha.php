<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcesoFecha extends Model
{
    protected $table = 'procesofecha';
    protected $primaryKey = 'profec_iCodigo';
    public $incrementing = true;

    protected $fillable = [
        'profec_iCodigo',
        'pro_iCodigo',
        'profec_dFecha',
        'profec_iActivo',
        'profec_vcUrlAnverso',
        'profec_vcUrlReverso',
    ];

    public function proceso(): BelongsTo
    {
        // Foreign key en esta tabla: pro_iCodigo -> clave primaria en proceso: pro_iCodigo
        return $this->belongsTo(Proceso::class, 'pro_iCodigo', 'pro_iCodigo');
    }

     public function localesMaestro(): BelongsToMany
    {
        return $this->belongsToMany(
            LocalesMaestro::class,
            'locales', 
            'profec_iCodigo',   
            'locma_iCodigo', 
        );
    }

    public function experiencias(): HasMany
    {
        return $this->hasMany(ExperienciaAdmision::class, 'profec_iCodigo');
    }

     protected $casts = [
        'profec_iActivo' => 'boolean', 
        
    ];
}
