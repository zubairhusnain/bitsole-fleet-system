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
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);
        $middleware->web(append: [
            \App\Http\Middleware\DemoReadOnly::class,
            \App\Http\Middleware\LogSystemActivity::class,
            \App\Http\Middleware\SanitizeUserFacingMessages::class,
        ]);
        $middleware->alias([
            'abort.client' => \App\Http\Middleware\AbortIfClientDisconnected::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([
            \App\Exceptions\ClientDisconnectedException::class,
        ]);
        $exceptions->render(function (\App\Exceptions\ClientDisconnectedException $e) {
            return response('', 499);
        });
    })->create();
