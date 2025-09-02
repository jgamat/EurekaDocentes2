<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Planilla extends Model
{
    use HasFactory;

    /**
     * El nombre de la tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'planilla';

    /**
     * La clave primaria para el modelo.
     *
     * @var string
     */
    protected $primaryKey = 'pla_id';

    /**
     * Los atributos que se pueden asignar masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'pro_iCodigo',
        'profec_iCodigo',
        'tipo_iCodigo',
        'pla_iNumero',
        'pla_iPaginaInicio',
        'pla_IPaginaFin', 
        'pla_iAdicional',
        'pla_iVersion',
        'pla_bActivo',
        'pla_id_padre',
        'pla_vcMotivoCambio',
        'user_id',
        'pla_vcIp',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'pla_bActivo' => 'boolean', // Convierte el TINYINT(1) a true/false
        'pla_iAdicional' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    /**
     * Obtiene los registros de docentes asignados a esta versión de la planilla.
     */
    public function docentes(): HasMany
    {
        // Una planilla (una versión específica) tiene muchas asignaciones de docentes.
        return $this->hasMany(PlanillaDocente::class, 'pla_id', 'pla_id');
    }

    /**
     * Si esta planilla es una versión, obtiene la planilla original (padre).
     */
    public function versionOriginal(): BelongsTo
    {
        // Una versión de planilla pertenece a una planilla padre.
        return $this->belongsTo(Planilla::class, 'pla_id_padre', 'pla_id');
    }

    /**
     * Si esta planilla es la original, obtiene todas sus versiones/revisiones.
     */
    public function versiones(): HasMany
    {
        // Una planilla padre tiene muchas versiones hijas.
        return $this->hasMany(Planilla::class, 'pla_id_padre', 'pla_id');
    }

    /**
     * Obtiene el usuario que creó/modificó el registro.
     * (Asumiendo que tienes un modelo User)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    
    public function tipo(): BelongsTo
    {
         return $this->belongsTo(Tipo::class, 'tipo_iCodigo', 'tipo_iCodigo');
    }
}