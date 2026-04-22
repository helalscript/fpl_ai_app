<?php

use App\Helpers\Helper;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, $request) {

            if ($request->is('api/*')) {

                // Validation
                if ($e instanceof ValidationException) {
                    return Helper::jsonErrorResponse($e->getMessage(), 422, $e->errors());
                }

                // Model not found
                if ($e instanceof ModelNotFoundException) {
                    return Helper::jsonErrorResponse($e->getMessage(), 404);
                }

                // Auth
                if ($e instanceof AuthenticationException) {
                    return Helper::jsonErrorResponse($e->getMessage(), 401);
                }

                // Authorization
                if ($e instanceof AuthorizationException) {
                    return Helper::jsonErrorResponse($e->getMessage(), 403);
                }

                // Rate limit
                if ($e instanceof TooManyRequestsHttpException) {
                    return Helper::jsonErrorResponse(
                        'Too many attempts. Please try again later.',
                        429,
                        ['retry_after' => $e->getHeaders()['Retry-After'] ?? 60]
                    );
                }

                // Default
                $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

                return Helper::jsonErrorResponse($e->getMessage(), $statusCode);
            }

            // fallback web / default
            // return $e;
        });
    })->create();
