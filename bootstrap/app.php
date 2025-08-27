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
        // Remove Sanctum middleware since it's not installed
        $middleware->api(prepend: [
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            // \App\Http\Middleware\ForceJsonResponse::class,
        ]);
        
        // $middleware->alias([
        //     'auth.api' => \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Remove duplicate exception handler to prevent double JSON responses
        // $exceptions->render(function (\Throwable $e, $request) {
        //     if ($request->is('api/*')) {
        //         return response()->json([
        //             'success' => false,
        //             'message' => $e->getMessage(),
        //             'error' => get_class($e)
        //         ], 500);
        //     }
        // });
    })->create();
