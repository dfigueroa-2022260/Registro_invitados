<?php

use App\Exceptions\ApiError;
use App\Http\Middleware\RequiereAdmin;
use App\Http\Middleware\RequiereInvitado;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Detrás de un túnel (ngrok, cloudflared...) la IP y el https reales vienen en las cabeceras X-Forwarded-*.
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'api.invitado' => RequiereInvitado::class,
            'api.admin' => RequiereAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontReport([ApiError::class]);
    })->create();
