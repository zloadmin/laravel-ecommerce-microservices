<?php

namespace Ecommerce\Shared\Messaging\Console;

use Ecommerce\Shared\Messaging\Event;
use Ecommerce\Shared\Messaging\EventConsumer;
use Ecommerce\Shared\Messaging\RabbitMq;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

class ConsumeEvents extends Command
{
    protected $signature = 'events:consume {--max-messages=0 : Stop after this many messages (0 = run forever)}';

    protected $description = 'Consume domain events from RabbitMQ and dispatch them to handlers';

    public function handle(RabbitMq $rabbit, EventConsumer $consumer): int
    {
        if ($consumer->bindings() === []) {
            $this->components->error('No handlers are configured in messaging.handlers.');

            return self::FAILURE;
        }

        $queue = config('messaging.service').'.events';
        $deadLetterExchange = $rabbit->exchange.'.dlx';
        $channel = $rabbit->channel();

        // Failed events are parked in "<service>.events.dlq" for inspection.
        $channel->exchange_declare($deadLetterExchange, 'direct', passive: false, durable: true, auto_delete: false);
        $channel->queue_declare("$queue.dlq", passive: false, durable: true, exclusive: false, auto_delete: false);
        $channel->queue_bind("$queue.dlq", $deadLetterExchange, $queue);

        $channel->queue_declare($queue, passive: false, durable: true, exclusive: false, auto_delete: false, arguments: new AMQPTable([
            'x-dead-letter-exchange' => $deadLetterExchange,
            'x-dead-letter-routing-key' => $queue,
        ]));

        foreach ($consumer->bindings() as $pattern) {
            $channel->queue_bind($queue, $rabbit->exchange, $pattern);
        }

        $handled = 0;
        $limit = (int) $this->option('max-messages');

        $channel->basic_qos(0, 10, false);
        $channel->basic_consume($queue, callback: function (AMQPMessage $message) use ($consumer, &$handled): void {
            $this->dispatch($message, $consumer);
            $handled++;
        });

        $running = true;
        $this->trap([SIGTERM, SIGINT], function () use (&$running): void {
            $running = false;
        });

        $this->components->info("Consuming from [$queue].");

        while ($running && $channel->is_consuming() && ($limit === 0 || $handled < $limit)) {
            try {
                $channel->wait(null, false, 1);
            } catch (AMQPTimeoutException) {
                // No message within the timeout; loop to check for shutdown.
            }
        }

        $rabbit->reset();

        return self::SUCCESS;
    }

    private function dispatch(AMQPMessage $message, EventConsumer $consumer): void
    {
        try {
            $event = Event::fromJson($message->getBody());
        } catch (InvalidArgumentException $e) {
            Log::error('Unreadable event dead-lettered.', ['error' => $e->getMessage()]);
            $message->reject(false);

            return;
        }

        try {
            $processed = $consumer->process($event);
            $message->ack();

            Log::info($processed ? 'Event handled.' : 'Duplicate event skipped.', ['id' => $event->id, 'type' => $event->type]);
        } catch (Throwable $e) {
            // One immediate retry, then the dead-letter queue.
            $retry = ! $message->isRedelivered();

            Log::error($retry ? 'Event handler failed; retrying.' : 'Event handler failed; dead-lettered.', [
                'id' => $event->id,
                'type' => $event->type,
                'error' => $e->getMessage(),
            ]);

            $retry ? $message->nack(true) : $message->reject(false);
        }
    }
}
