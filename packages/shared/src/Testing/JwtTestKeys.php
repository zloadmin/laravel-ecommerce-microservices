<?php

namespace Ecommerce\Shared\Testing;

use Firebase\JWT\JWT;
use Illuminate\Support\Str;

/**
 * Test support: a throwaway RSA key pair plus a way to mint access tokens,
 * so any service can test protected endpoints without the auth service.
 * Call JwtTestKeys::install() in TestCase::setUp().
 */
final class JwtTestKeys
{
    /** @var array{private: string, public: string}|null */
    private static ?array $paths = null;

    public static function install(): void
    {
        if (self::$paths === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $private);

            $dir = sys_get_temp_dir().'/ecommerce-test-keys-'.bin2hex(random_bytes(6));
            mkdir($dir, 0700, true);
            file_put_contents("$dir/private.pem", $private);
            file_put_contents("$dir/public.pem", openssl_pkey_get_details($key)['key']);

            self::$paths = ['private' => "$dir/private.pem", 'public' => "$dir/public.pem"];
        }

        config([
            'ecommerce.jwt.private_key_path' => self::$paths['private'],
            'ecommerce.jwt.public_key_path' => self::$paths['public'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $claims  overrides, e.g. ['exp' => time() - 1]
     */
    public static function token(?string $userId = null, string $role = 'customer', array $claims = []): string
    {
        return JWT::encode($claims + [
            'iss' => config('ecommerce.jwt.issuer'),
            'sub' => $userId ?? (string) Str::uuid7(),
            'role' => $role,
            'iat' => time(),
            'exp' => time() + 600,
            'jti' => (string) Str::uuid7(),
        ], file_get_contents(self::$paths['private']), 'RS256');
    }
}
