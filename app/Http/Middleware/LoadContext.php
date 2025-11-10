<?php

namespace App\Http\Middleware;

use App\Support\CurrentContext;
use Closure;
use Illuminate\Http\Request;

class LoadContext
{
    public function handle(Request $request, Closure $next)
    {
        /** @var CurrentContext $ctx */
        $ctx = app(CurrentContext::class);

        // Optional override from query
        $procesoId = $request->query('proceso');
        $fechaId = $request->query('fecha');
        if ($procesoId || $fechaId) {
            $ctx->overrideFromQuery($procesoId ? (int) $procesoId : null, $fechaId ? (int) $fechaId : null);
        }

        $ctx->ensureLoaded();
        $ctx->ensureValid();

        return $next($request);
    }
}
