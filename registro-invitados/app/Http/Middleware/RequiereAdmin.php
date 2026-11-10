<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiError;
use Closure;
use Illuminate\Http\Request;

class RequiereAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->session()->get('es_admin')) {
            throw new ApiError('Acceso solo para administradores', 401);
        }

        return $next($request);
    }
}
