<?php

namespace Ecommerce\Shared;

use Ecommerce\Shared\Auth\JwtVerifier;
use Ecommerce\Shared\Http\Middleware\Authenticate;
use Ecommerce\Shared\Http\Middleware\RequireRole;
use Ecommerce\Shared\Messaging\Console\ConsumeEvents;
use Ecommerce\Shared\Messaging\Console\RelayOutbox;
use Ecommerce\Shared\Messaging\EventConsumer;
use Ecommerce\Shared\Messaging\EventPublisher;
use Ecommerce\Shared\Messaging\RabbitMq;
use Ecommerce\Shared\Messaging\RabbitMqPublisher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

final class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ecommerce.php', 'ecommerce');
        $this->mergeConfigFrom(__DIR__.'/../config/messaging.php', 'messaging');

        $this->app->singleton(JwtVerifier::class, fn () => new JwtVerifier(
            config('ecommerce.jwt.public_key_path'),
            config('ecommerce.jwt.issuer'),
        ));

        $this->app->singleton(RabbitMq::class, fn () => new RabbitMq(
            config('messaging.connection'),
            config('messaging.exchange'),
        ));
        $this->app->singleton(EventPublisher::class, RabbitMqPublisher::class);
        $this->app->singleton(RabbitMqPublisher::class);
        $this->app->bind(EventConsumer::class, fn (Application $app) => new EventConsumer($app, config('messaging.handlers')));
    }

    public function boot(Router $router): void
    {
        // Usage: ->middleware('jwt'), ->middleware('jwt:optional'), ->middleware('role:admin')
        $router->aliasMiddleware('jwt', Authenticate::class);
        $router->aliasMiddleware('role', RequireRole::class);

        if (config('messaging.enabled')) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

            if ($this->app->runningInConsole()) {
                $this->commands([RelayOutbox::class, ConsumeEvents::class]);
            }
        }
    }
}
