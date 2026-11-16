<?php

namespace App\Http\Controllers;

use Ecommerce\Shared\Http\ApiError;
use Ecommerce\Shared\Http\Middleware\CorrelationId;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ProxyController extends Controller
{
    /** Headers that describe one hop and must not be forwarded in either direction. */
    private const HOP_BY_HOP = [
        'host', 'connection', 'keep-alive', 'proxy-authenticate', 'proxy-authorization',
        'te', 'trailer', 'transfer-encoding', 'upgrade', 'content-length',
    ];

    public function __invoke(Request $request, string $resource): Response
    {
        $service = config("gateway.routes.$resource");

        if ($service === null) {
            return ApiError::response('not_found', 'Unknown resource.', 404);
        }

        // Same path on the upstream; the raw query string is kept untouched.
        $url = rtrim(config("gateway.services.$service"), '/').$request->getPathInfo();

        if (($query = $request->server->get('QUERY_STRING')) !== null && $query !== '') {
            $url .= '?'.$query;
        }

        $headers = collect($request->headers->all())
            ->except(self::HOP_BY_HOP)
            ->map(fn (array $values) => implode(', ', $values))
            ->put('X-Forwarded-For', $request->ip())
            ->put('X-Forwarded-Host', $request->getHost())
            ->put('X-Forwarded-Proto', $request->getScheme())
            ->all();

        try {
            // Raw body passthrough. Multipart uploads are not supported yet:
            // PHP does not expose them through php://input.
            $upstream = Http::withHeaders($headers)
                ->connectTimeout(config('gateway.connect_timeout'))
                ->timeout(config('gateway.timeout'))
                ->withOptions(['allow_redirects' => false])
                ->withBody($request->getContent(), $request->headers->get('Content-Type', 'application/json'))
                ->send($request->getMethod(), $url);
        } catch (ConnectionException $e) {
            Log::warning('Upstream unreachable', ['service' => $service, 'url' => $url, 'error' => $e->getMessage()]);

            return ApiError::response('service_unavailable', "The $service service is unavailable.", 503);
        }

        $responseHeaders = collect($upstream->headers())
            ->reject(fn ($_, string $name) => in_array(strtolower($name), self::HOP_BY_HOP, true)
                || strcasecmp($name, CorrelationId::HEADER) === 0)
            ->all();

        return response($upstream->body(), $upstream->status())->withHeaders($responseHeaders);
    }
}
