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

        if (! $token instanceof PersonalAccessToken || ! in_array($permiso, $token->abilities, true)) {
            return response()->json(['message' => 'El token no tiene permiso para esta operación.'], 403);
        }

        return $next($request);
    }
}
