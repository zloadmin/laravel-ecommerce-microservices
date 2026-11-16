<?php

namespace Ecommerce\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accepts or generates an X-Correlation-ID, exposes it to logs (via Context)
 * and echoes it on the response so one request can be traced across services.
 */
final class CorrelationId
{
    public const HEADER = 'X-Correlation-ID';

    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->headers->get(self::HEADER);

        if (! is_string($id) || ! preg_match('/^[A-Za-z0-9\-_.]{8,128}$/', $id)) {
            $id = (string) Str::uuid7();
        }

        $request->headers->set(self::HEADER, $id);
        Context::add('correlation_id', $id);

        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }
}
