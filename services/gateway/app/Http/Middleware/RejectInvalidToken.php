<?php

namespace App\Http\Middleware;

use Closure;
use Ecommerce\Shared\Auth\JwtVerifier;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stops requests carrying a forged or expired access token at the edge.
 * Whether a route needs a token at all is decided by the owning service,
 * which verifies the token again itself.
 */
class RejectInvalidToken
{
    public function __construct(private readonly JwtVerifier $verifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        // /auth/* is exempt: clients often attach their expired access token
        // to the very refresh call that is meant to replace it.
        if ($token !== null && $token !== '' && $request->route('resource') !== 'auth') {
            $this->verifier->verify($token);
        }

        return $next($request);
    }
}
