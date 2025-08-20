<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Credencial extends Model
{
    protected $table = 'credencial';
    protected $primaryKey = 'cred_id';

    public $incrementing = true;
    protected $keyType = 'int';
 

    protected $fillable = [
        'cred_iCodigo',
        'cred_iCodigo',
         'cred_vcDni',
        'cred_dtFechaEntrega',
        'user_id',       
        'cred_vcIp',
        'created_at',
        'updated_at',
        
       
       
       
    ];

    public function usuario()
    {
    return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
