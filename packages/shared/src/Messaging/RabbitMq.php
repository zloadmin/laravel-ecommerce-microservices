<?php

namespace Ecommerce\Shared\Messaging;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Throwable;

/**
 * Lazily opened RabbitMQ connection with the shared events exchange declared.
 */
final class RabbitMq
{
    private ?AMQPStreamConnection $connection = null;

    private ?AMQPChannel $channel = null;

    /** @param  array{host: string, port: int, user: string, password: string, vhost: string}  $config */
    public function __construct(
        private readonly array $config,
        public readonly string $exchange,
    ) {}

    public function channel(): AMQPChannel
    {
        if ($this->channel === null || ! $this->channel->is_open()) {
            $this->connection = new AMQPStreamConnection(
                $this->config['host'],
                $this->config['port'],
                $this->config['user'],
                $this->config['password'],
                $this->config['vhost'],
                heartbeat: 30,
            );

            $this->channel = $this->connection->channel();
            $this->channel->exchange_declare($this->exchange, 'topic', passive: false, durable: true, auto_delete: false);
        }

        return $this->channel;
    }

    /** Drop the connection so the next channel() call reconnects. */
    public function reset(): void
    {
        try {
            $this->channel?->close();
            $this->connection?->close();
        } catch (Throwable) {
            // Already broken; nothing to clean up.
        }

        $this->channel = null;
        $this->connection = null;
    }
}
