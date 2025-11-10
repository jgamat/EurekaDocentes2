<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    use HasFactory;

    protected $table = 'user_preferences';

    protected $fillable = [
        'user_id', 'key', 'value',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
