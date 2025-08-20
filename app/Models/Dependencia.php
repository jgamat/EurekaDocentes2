<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dependencia extends Model
{
     protected $table = 'dependencia';
    protected $primaryKey = 'dep_iCodigo';

    public $incrementing = false;
    protected $keyType = 'string'; 
}









