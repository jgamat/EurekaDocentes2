<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitLogin
{
    public function handle(Request $request, Closure $next)
    {
        // Filament login uses Livewire; detect authenticate calls and rate limit
        $isLivewire = $request->headers->has('X-Livewire');
        if ($isLivewire) {
            $content = $request->getContent() ?? '';
            if ($content && str_contains($content, '"method":"authenticate"')) {
                $key = 'login:' . ($request->ip() ?? 'unknown');
                if (RateLimiter::tooManyAttempts($key, 5)) {
                    $seconds = RateLimiter::availableIn($key);
                    Log::warning('Login throttled', ['ip' => $request->ip(), 'path' => $request->path()]);
                    return response()->json([
                        'message' => 'Too many login attempts. Please try again in ' . $seconds . ' seconds.',
                    ], 429);
                }
                RateLimiter::hit($key, 60); // decay after 60 seconds
            }
        }

        return $next($request);
    }
}
