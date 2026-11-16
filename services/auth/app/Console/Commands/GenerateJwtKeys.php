<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateJwtKeys extends Command
{
    protected $signature = 'jwt:generate-keys {--force : Overwrite existing keys (invalidates all issued tokens)}';

    protected $description = 'Generate the RSA key pair used to sign and verify access tokens';

    public function handle(): int
    {
        $privatePath = config('ecommerce.jwt.private_key_path');
        $publicPath = config('ecommerce.jwt.public_key_path');

        if (file_exists($privatePath) && ! $this->option('force')) {
            $this->components->info('JWT keys already exist. Use --force to replace them.');

            return self::SUCCESS;
        }

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        if ($key === false || ! openssl_pkey_export($key, $private)) {
            $this->components->error('Could not generate an RSA key pair: '.openssl_error_string());

            return self::FAILURE;
        }

        foreach ([dirname($privatePath), dirname($publicPath)] as $dir) {
            is_dir($dir) || mkdir($dir, 0755, true);
        }

        file_put_contents($privatePath, $private);
        chmod($privatePath, 0600);
        file_put_contents($publicPath, openssl_pkey_get_details($key)['key']);

        $this->components->info('JWT keys written to '.dirname(realpath($privatePath)));

        return self::SUCCESS;
    }
}
