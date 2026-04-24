<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuthenticationLog extends Model
{
    protected $table = 'authentication_log';

    public $timestamps = false;

    protected $fillable = [
        'authenticatable_type',
        'authenticatable_id',
        'ip_address',
        'user_agent',
        'login_at',
        'login_successful',
        'logout_at',
        'cleared_by_user',
        'location',
    ];

    protected $casts = [
        'login_at' => 'datetime',
        'logout_at' => 'datetime',
        'login_successful' => 'boolean',
        'cleared_by_user' => 'boolean',
        'location' => 'array',
    ];

    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }
}
