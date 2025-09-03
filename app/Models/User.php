<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;  
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Rappasoft\LaravelAuthenticationLog\Traits\AuthenticationLoggable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use Notifiable;
    use HasRoles;
    use AuthenticationLoggable;

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        // Aquí puedes poner lógica compleja en el futuro, como verificar un rol.
        // Por ahora, con que devuelva 'true' es suficiente para permitir el acceso.
        return true;
    }

}
