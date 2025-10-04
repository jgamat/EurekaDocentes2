<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportJobLog extends Model
{
    protected $table = 'import_job_logs';

    protected $fillable = [
        'user_id',
        'filename_original',
        'file_path',
        'total_filas',
        'importadas',
        'omitidas',
        'errores',
    ];

    protected $casts = [
        'errores' => 'array',
    ];
}
