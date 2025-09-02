<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntregaCredencialRow extends Model
{
    // Modelo sólo para lectura sobre el alias del subquery
    protected $table = 'u';
    protected $primaryKey = 'row_key';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public function save(array $options = [])
    {
        return false; // Nunca guardar
    }

    public function delete()
    {
        return false; // Nunca borrar
    }
}
