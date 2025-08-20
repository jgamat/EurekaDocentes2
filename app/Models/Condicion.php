<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Condicion extends Model
{
    protected $table = 'condicion';
    protected $primaryKey = 'con_iCodigo';

    public $incrementing = true;
   
}
