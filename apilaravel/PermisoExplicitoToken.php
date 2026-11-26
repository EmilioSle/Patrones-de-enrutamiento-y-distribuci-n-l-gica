<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class PermisoExplicitoToken
{
    public function handle(Request $request, Closure $next, string $permiso): Response
    {
        $token = $request->user()->currentAccessToken();

        abort_unless($token instanceof PersonalAccessToken && in_array($permiso, $token->abilities, true), 403);

        return $next($request);
    }
}
