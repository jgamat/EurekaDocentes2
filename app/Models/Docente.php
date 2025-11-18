<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;


class Docente extends Model
{
  
    protected $table = 'docente';
    protected $primaryKey = 'doc_id';

    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'doc_vcTipo',
         'doc_vcCodigo',
        'doc_vcDni',
        'doc_vcPaterno',
        'doc_vcMaterno',
        'doc_vcNombre',
        'cat_iCodigo',
        'tipo_iCodigo',       
        'con_iCodigo',
         'est_iCodigo',
        'dep_iCodigo',
        'doc_vcCelular',
        'doc_vcEmailUNMSM',
        'doc_vcEmail',
         'user_idImpresion',
         'prodoc_vcIpImpresion',
       
    ];

    public function getNombreCompletoAttribute(): string
    {
    return "{$this->doc_vcPaterno} {$this->doc_vcMaterno} {$this->doc_vcNombre}";
    }

    public function dependencia()
    {
    return $this->belongsTo(Dependencia::class, 'dep_iCodigo', 'dep_iCodigo');
    }

    public function categoria()
    {
    return $this->belongsTo(Categoria::class, 'cat_iCodigo', 'cat_iCodigo');
    }

    public function condicion()
    {
    return $this->belongsTo(Condicion::class, 'con_iCodigo', 'con_iCodigo');
    }

    public function estado()
    {
    return $this->belongsTo(Estado::class, 'est_iCodigo', 'est_iCodigo');
    } 
    
    public function tipo()
    {
    return $this->belongsTo(Tipo::class, 'tipo_iCodigo', 'tipo_iCodigo');
    } 
    
    public function asignaciones(): HasMany
    {
        return $this->hasMany(ProcesoDocente::class, 'doc_vcCodigo', 'doc_vcCodigo');
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
            // Requerir que todos los tokens aparezcan en alguno de los campos básicos
            foreach ($tokens as $t) {
                $like = "%" . $t . "%";
                $q->where(function (Builder $qq) use ($like) {
                    $qq->where('doc_vcPaterno', 'like', $like)
                       ->orWhere('doc_vcMaterno', 'like', $like)
                       ->orWhere('doc_vcNombre', 'like', $like);
                });
            }

            // Atajos por concatenaciones completas
            $likeAll = "%" . $s . "%";
            $q->orWhereRaw("CONCAT_WS(' ', doc_vcPaterno, doc_vcMaterno) LIKE ?", [$likeAll])
              ->orWhereRaw("CONCAT_WS(' ', doc_vcPaterno, doc_vcMaterno, doc_vcNombre) LIKE ?", [$likeAll])
              // También permitir búsqueda por DNI y Código dentro del mismo bloque
              ->orWhere('doc_vcDni', 'like', $likeAll)
              ->orWhere('doc_vcCodigo', 'like', $likeAll);
        });
    }
}
