<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

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


    /**
     * Busca por apellidos y nombres con soporte multi-token.
     * Admite entradas como "Paterno Materno" o "Paterno Materno Nombre".
     * También mantiene coincidencias por DNI y Código.
     */
    public function scopeSearchPerson(Builder $query, string $search): Builder
    {
        $s = trim(preg_replace('/\s+/', ' ', $search));
        if ($s === '') {
            return $query;
        }

        $tokens = explode(' ', $s);

        return $query->where(function (Builder $q) use ($tokens, $s) {
            foreach ($tokens as $t) {
                $like = "%" . $t . "%";
                $q->where(function (Builder $qq) use ($like) {
                    $qq->where('alu_vcPaterno', 'like', $like)
                       ->orWhere('alu_vcMaterno', 'like', $like)
                       ->orWhere('alu_vcNombre', 'like', $like);
                });
            }

            $likeAll = "%" . $s . "%";
            $q->orWhereRaw("CONCAT_WS(' ', alu_vcPaterno, alu_vcMaterno) LIKE ?", [$likeAll])
              ->orWhereRaw("CONCAT_WS(' ', alu_vcPaterno, alu_vcMaterno, alu_vcNombre) LIKE ?", [$likeAll])
              ->orWhere('alu_vcDni', 'like', $likeAll)
              ->orWhere('alu_vcCodigo', 'like', $likeAll);
        });
    }
}
