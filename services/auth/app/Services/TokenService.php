<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class TokenService
{
    /**
     * Issue an access token (RS256 JWT) and a fresh refresh token.
     *
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token: string}
     */
    public function issue(User $user): array
    {
        $ttl = config('tokens.access_ttl');
        $now = time();

        $accessToken = JWT::encode([
            'iss' => config('ecommerce.jwt.issuer'),
            'sub' => $user->id,
            'role' => $user->role,
            'iat' => $now,
            'exp' => $now + $ttl,
            'jti' => (string) Str::uuid7(),
        ], $this->privateKey(), 'RS256');

        $refreshToken = Str::random(64);

        $user->refreshTokens()->create([
            'token_hash' => RefreshToken::hash($refreshToken),
            'expires_at' => now()->addSeconds(config('tokens.refresh_ttl')),
        ]);

        return [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
            'refresh_token' => $refreshToken,
        ];
    }

    /**
     * Exchange a refresh token for a new token pair. Refresh tokens are single-use:
     * presenting one that was already rotated means it leaked, so every session
     * of that user is revoked.
     *
     * @return array{0: User, 1: array<string, mixed>}|null null when the token is not usable
     */
    public function rotate(string $plainToken): ?array
    {
        return DB::transaction(function () use ($plainToken): ?array {
            $token = RefreshToken::where('token_hash', RefreshToken::hash($plainToken))->lockForUpdate()->first();

            if ($token === null) {
                return null;
            }

            if (! $token->isUsable()) {
                if ($token->revoked_at !== null) {
                    $this->revokeAll($token->user_id);
                }

                return null;
            }

            $token->update(['revoked_at' => now()]);

            return [$token->user, $this->issue($token->user)];
        });
    }

    public function revoke(string $plainToken): void
    {
        RefreshToken::where('token_hash', RefreshToken::hash($plainToken))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function revokeAll(string $userId): void
    {
        RefreshToken::where('user_id', $userId)->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    private function privateKey(): string
    {
        $path = config('ecommerce.jwt.private_key_path');
        $key = @file_get_contents($path);

        if ($key === false) {
            throw new RuntimeException("JWT private key not found at $path. Run: php artisan jwt:generate-keys");
        }

        return $key;
    }
}
