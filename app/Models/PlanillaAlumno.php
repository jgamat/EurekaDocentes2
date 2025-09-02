<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class PlanillaAlumno extends Model
{
     use HasFactory;

    /**
     * El nombre de la tabla asociada con el modelo.
     * Laravel podría inferir "planilla_docentes", por lo que es mejor especificarlo.
     *
     * @var string
     */
    protected $table = 'planillaAlumno';

    /**
     * La clave primaria para el modelo.
     *
     * @var string
     */
    protected $primaryKey = 'plaalu_id';

    /**
     * Los atributos que se pueden asignar masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'pla_id',
        'alu_vcCodigo',
        
        'plaalu_iImpreso',
        'plaalu_iOrden',
        'plaalu_dtFechaImpresion',
        'user_id',
        'plaalu_vcIp',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'plaalu_iImpreso' => 'boolean',
        'plaalu_dtFechaImpresion' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    /**
     * Obtiene la planilla a la que esta asignación pertenece.
     */
    public function planilla(): BelongsTo
    {
        // Una asignación de docente pertenece a una versión específica de una planilla.
        return $this->belongsTo(Planilla::class, 'pla_id', 'pla_id');
    }

    public function alumno()
    {
    return $this->belongsTo(Alumno::class, 'alu_vcCodigo', 'alu_vcCodigo');
    }

    /**
     * Obtiene la información del docente.
     * (Asumiendo que tienes un modelo Docente donde la clave primaria es 'vcCodigo')
     */
    // public function docente(): BelongsTo
    // {
    //     return $this->belongsTo(Docente::class, 'doc_vcCodigo', 'vcCodigo');
    // }

    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Aquí puedes añadir las relaciones con Local, ExperienciaAdmision, etc.
    // Ejemplo:
    // public function local(): BelongsTo
    // {
    //     return $this->belongsTo(Local::class, 'loc_iCodigo', 'loc_iCodigo');
    // }
}
