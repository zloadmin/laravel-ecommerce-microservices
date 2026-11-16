<?php

namespace Ecommerce\Shared;

use Ecommerce\Shared\Auth\JwtVerifier;
use Ecommerce\Shared\Http\Middleware\Authenticate;
use Ecommerce\Shared\Http\Middleware\RequireRole;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

final class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ecommerce.php', 'ecommerce');

        $this->app->singleton(JwtVerifier::class, fn () => new JwtVerifier(
            config('ecommerce.jwt.public_key_path'),
            config('ecommerce.jwt.issuer'),
        ));
    }

    public function boot(Router $router): void
    {
        // Usage: ->middleware('jwt'), ->middleware('jwt:optional'), ->middleware('role:admin')
        $router->aliasMiddleware('jwt', Authenticate::class);
        $router->aliasMiddleware('role', RequireRole::class);
    }
}
