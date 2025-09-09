<?php

namespace App\Filament\Resources;

use Illuminate\Support\Facades\Auth;
use Tapp\FilamentAuthenticationLog\Resources\AuthenticationLogResource as VendorAuthenticationLogResource;

class AuthenticationLogResource extends VendorAuthenticationLogResource
{
    protected static function userHasAccess(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        $roles = config('filament-authentication-log.allowed_roles', ['super_admin']);
        return $user->hasAnyRole($roles);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return parent::shouldRegisterNavigation() && static::userHasAccess();
    }

    public static function canViewAny(): bool
    {
        return static::userHasAccess();
    }
}
