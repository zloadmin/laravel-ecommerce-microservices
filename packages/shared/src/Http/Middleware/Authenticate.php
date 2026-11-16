<?php

namespace Ecommerce\Shared\Http\Middleware;

use Closure;
use Ecommerce\Shared\Auth\InvalidTokenException;
use Ecommerce\Shared\Auth\JwtVerifier;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the bearer token and exposes the caller as $request->user().
 * In "optional" mode a missing token is allowed, but a present token must still be valid.
 */
final class Authenticate
{
    public function __construct(private readonly JwtVerifier $verifier) {}

    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            if ($mode === 'optional') {
                return $next($request);
            }

            throw new InvalidTokenException('Missing access token.');
        }

        $user = $this->verifier->verify($token);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
