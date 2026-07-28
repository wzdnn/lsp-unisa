<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ReadOnlySession
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $excludedRoutes = ['/api/login', '/api/logout'];

        if (!in_array($request->getPathInfo(), $excludedRoutes)) {
            $response->headers->remove('Set-Cookie');
        }

        return $response;
    }
}

