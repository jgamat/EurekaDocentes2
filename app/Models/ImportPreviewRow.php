<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportPreviewRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id','token','row','codigo','dni','nombres','cargo','local','fecha','errores','warnings','valid'
    ];

    protected $casts = [
        'errores' => 'array',
        'warnings' => 'array',
        'valid' => 'boolean',
    ];
}
