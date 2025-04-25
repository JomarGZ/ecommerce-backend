<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('api/v1')
                ->group(base_path('routes/api_v1.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (Throwable $e, $request) {
            info($e);
            if ($request->is("api/*") || $request->is("sanctum/csrf-cookie")) {
                if ($e instanceof QueryException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Server error. Please try again later..',
                        'code' => Response::HTTP_INTERNAL_SERVER_ERROR
                    ], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
                if ($e instanceof NotFoundHttpException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Resource not found.',
                        'code' => $e->getStatusCode()
                    ], $e->getStatusCode());
                }
                if ($e instanceof HttpException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid or expired token. Please refresh the page.',
                        'code' => $e->getStatusCode()
                    ], $e->getStatusCode());
                }
            }
        });
    })->create();
