<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Solo el grupo "web" (páginas HTML de Inertia/Blade): el vhost de
        // nginx en el borde (container/nginx-reverse.conf) ya pone las
        // mismas cabeceras para TODAS las respuestas, incluida /up y
        // /api/*. Aplicar esto también de forma global las duplicaba
        // (dos X-Frame-Options, dos CSP con connect-src distinto).
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->alias([
            'authentik.jwt' => \App\Http\Middleware\AuthentikJwt::class,
            'role' => \App\Http\Middleware\EnsureRole::class,
            'recaptcha' => \App\Http\Middleware\VerifyRecaptcha::class,
            'locale' => \App\Http\Middleware\DetectLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
