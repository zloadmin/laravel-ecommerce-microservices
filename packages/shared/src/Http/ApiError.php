<?php

namespace Ecommerce\Shared\Http;

use Illuminate\Http\JsonResponse;

/**
 * The single error shape every service returns:
 * {"error": {"code": "not_found", "message": "...", "details": {...}}}
 */
final class ApiError
{
    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, string>  $headers
     */
    public static function response(string $code, string $message, int $status, array $details = [], array $headers = []): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];

        if ($details !== []) {
            $error['details'] = $details;
        }

        return new JsonResponse(['error' => $error], $status, $headers);
    }
}
