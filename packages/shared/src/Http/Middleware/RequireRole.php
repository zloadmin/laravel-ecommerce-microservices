<?php

namespace Ecommerce\Shared\Http\Middleware;

use Closure;
use Ecommerce\Shared\Auth\AuthenticatedUser;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Must run after the "jwt" middleware. */
final class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user instanceof AuthenticatedUser || ! in_array($user->role, $roles, true)) {
            throw new AccessDeniedHttpException('You are not allowed to perform this action.');
        }

        return $next($request);
    }
}
