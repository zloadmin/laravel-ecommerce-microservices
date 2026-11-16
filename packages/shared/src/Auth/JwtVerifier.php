<?php

namespace Ecommerce\Shared\Auth;

use DomainException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

final class JwtVerifier
{
    private ?string $publicKey = null;

    public function __construct(
        private readonly string $publicKeyPath,
        private readonly string $issuer,
    ) {}

    /**
     * @throws InvalidTokenException when the token is malformed, expired, forged or from another issuer
     */
    public function verify(string $token): AuthenticatedUser
    {
        $key = new Key($this->publicKey(), 'RS256');

        try {
            $claims = JWT::decode($token, $key);
        } catch (UnexpectedValueException|DomainException|InvalidArgumentException $e) {
            throw new InvalidTokenException('Invalid access token.');
        }

        if (($claims->iss ?? null) !== $this->issuer || ! is_string($claims->sub ?? null) || $claims->sub === '') {
            throw new InvalidTokenException('Invalid access token.');
        }

        return new AuthenticatedUser($claims->sub, (string) ($claims->role ?? 'customer'));
    }

    private function publicKey(): string
    {
        if ($this->publicKey === null) {
            $key = @file_get_contents($this->publicKeyPath);

            if ($key === false) {
                // A deployment problem, not a client error: surface as 500, never as 401.
                throw new RuntimeException("JWT public key not found at {$this->publicKeyPath}.");
            }

            $this->publicKey = $key;
        }

        return $this->publicKey;
    }
}
