<?php

namespace Ecommerce\Shared\Auth;

/**
 * The caller's identity as stated by a verified access token.
 * Available as $request->user() behind the "jwt" middleware.
 */
final readonly class AuthenticatedUser
{
    public function __construct(
        public string $id,
        public string $role,
    ) {}

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
