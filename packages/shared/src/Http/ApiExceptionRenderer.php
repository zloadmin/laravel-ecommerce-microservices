<?php

namespace Ecommerce\Shared\Http;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders every exception in the ApiError format.
 * Usage in bootstrap/app.php: ApiExceptionRenderer::register($exceptions);
 */
final class ApiExceptionRenderer
{
    private const CODES = [
        400 => 'bad_request',
        401 => 'unauthenticated',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        422 => 'unprocessable',
        429 => 'too_many_requests',
        503 => 'service_unavailable',
        504 => 'gateway_timeout',
    ];

    public static function register(Exceptions $exceptions): void
    {
        $exceptions->render(fn (Throwable $e): JsonResponse => self::render($e));
    }

    public static function render(Throwable $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return ApiError::response('validation_failed', $e->getMessage(), $e->status, ['fields' => $e->errors()]);
        }

        if ($e instanceof AuthenticationException) {
            return ApiError::response('unauthenticated', 'Unauthenticated.', 401);
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $code = self::CODES[$status] ?? ($status >= 500 ? 'internal_error' : 'http_error');
            $message = $e->getMessage() !== '' ? $e->getMessage() : (JsonResponse::$statusTexts[$status] ?? 'Error');

            return ApiError::response($code, $message, $status, [], $e->getHeaders());
        }

        $details = config('app.debug')
            ? ['exception' => $e::class, 'message' => $e->getMessage(), 'file' => $e->getFile().':'.$e->getLine()]
            : [];

        return ApiError::response('internal_error', 'Internal server error.', 500, $details);
    }
}
