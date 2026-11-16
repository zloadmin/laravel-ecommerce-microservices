<?php

use Ecommerce\Shared\Http\ApiError;
use Ecommerce\Shared\Http\ApiExceptionRenderer;
use Ecommerce\Shared\Http\Middleware\CorrelationId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(CorrelationId::class);
        // Only the gateway can reach this service, so its X-Forwarded-* headers
        // are trusted and generated URLs use the public host.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The cart cannot be shown or changed without the catalog.
        // An outage there is an expected condition: one warning line, no stack trace.
        $exceptions->report(function (ConnectionException|RequestException $e): void {
            Log::warning('Catalog unavailable.', ['error' => $e->getMessage()]);
        })->stop();

        $exceptions->render(fn (ConnectionException|RequestException $e) => ApiError::response(
            'service_unavailable', 'The catalog is unavailable. Please try again.', 503,
        ));

        ApiExceptionRenderer::register($exceptions);
    })->create();
