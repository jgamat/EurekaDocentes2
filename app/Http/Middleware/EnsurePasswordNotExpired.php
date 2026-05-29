<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordNotExpired
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return $next($request);
        }

        $allowedRoutes = [
            'filament.admin.auth.logout',
            'filament.admin.pages.force-password-change',
        ];

        // Bypass for allowed routes, livewire internal endpoints (where we check the referring url), or impersonation
        if ($request->routeIs($allowedRoutes) || $request->is('livewire/update')) {
            return $next($request);
        }

        $maxDays = config('auth.password_expires_days', 90);

        $changedAt = $user->password_changed_at ?? $user->created_at;
        $daysSinceChange = $changedAt ? Carbon::parse($changedAt)->diffInDays(now()) : 0;

        $isExpired = $daysSinceChange >= $maxDays || $user->requires_password_change;

        if ($isExpired) {
            // If it's a livewire request, we might need to handle the redirect differently
            if ($request->header('X-Livewire')) {
                return redirect()->route('filament.admin.pages.force-password-change');
            }

            return redirect()->route('filament.admin.pages.force-password-change')->with('warning', 'Debes cambiar tu contraseña para continuar.');
        }

        return $next($request);
    }
}
