<?php

namespace Ecommerce\Shared\Messaging;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;

/**
 * Dispatches an incoming event to the configured handlers, exactly once.
 */
final class EventConsumer
{
    /** @param  array<string, class-string<EventHandler>|list<class-string<EventHandler>>>  $handlers */
    public function __construct(
        private readonly Container $container,
        private readonly array $handlers,
    ) {}

    /** @return list<string> binding patterns to subscribe the queue to */
    public function bindings(): array
    {
        return array_keys($this->handlers);
    }

    /** @return bool false when the event was already processed and has been skipped */
    public function process(Event $event): bool
    {
        if ($event->correlationId !== null) {
            Context::add('correlation_id', $event->correlationId);
        }

        return DB::transaction(function () use ($event): bool {
            $inserted = DB::table('processed_messages')->insertOrIgnore([
                'id' => $event->id,
                'type' => $event->type,
                'processed_at' => now(),
            ]);

            if ($inserted === 0) {
                return false;
            }

            foreach ($this->handlers as $pattern => $handlers) {
                if (! self::matches($pattern, $event->type)) {
                    continue;
                }

                foreach (Arr::wrap($handlers) as $handler) {
                    $this->container->make($handler)->handle($event);
                }
            }

            return true;
        });
    }

    /** AMQP topic matching: "*" is exactly one word, "#" is zero or more words. */
    public static function matches(string $pattern, string $type): bool
    {
        $regex = collect(explode('.', $pattern))
            ->map(fn (string $word) => match ($word) {
                '*' => '[^.]+',
                '#' => '.*',
                default => preg_quote($word, '/'),
            })
            ->implode('\.');

        // "a.#" must also match "a" itself.
        $regex = str_replace('\..*', '(\..*)?', $regex);

        return preg_match('/^'.$regex.'$/', $type) === 1;
    }
}
