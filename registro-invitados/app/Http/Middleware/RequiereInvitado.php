<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiError;
use Closure;
use Illuminate\Http\Request;

/** Deja pasar a quien ya se registró como invitado (o a un administrador). */
class RequiereInvitado
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->session()->has('invitado_id') && ! $request->session()->get('es_admin')) {
            throw new ApiError('Primero regístrate para consultar', 401);
        }

        return $next($request);
    }
}
