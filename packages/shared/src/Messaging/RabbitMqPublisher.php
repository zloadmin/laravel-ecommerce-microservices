<?php

namespace Ecommerce\Shared\Messaging;

use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;

final class RabbitMqPublisher implements EventPublisher
{
    private bool $confirmsEnabled = false;

    private bool $nacked = false;

    public function __construct(private readonly RabbitMq $rabbit) {}

    public function publish(string $type, string $messageId, string $body): void
    {
        $channel = $this->rabbit->channel();

        if (! $this->confirmsEnabled || ! $channel->is_open()) {
            $channel->confirm_select();
            $channel->set_nack_handler(function (): void {
                $this->nacked = true;
            });
            $this->confirmsEnabled = true;
        }

        $channel->basic_publish(
            new AMQPMessage($body, [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id' => $messageId,
                'type' => $type,
            ]),
            $this->rabbit->exchange,
            $type,
        );
    }

    public function confirm(): void
    {
        $this->rabbit->channel()->wait_for_pending_acks(10);

        if ($this->nacked) {
            $this->nacked = false;

            throw new RuntimeException('The broker rejected one or more messages.');
        }
    }

    /** Forget channel state after the connection was reset. */
    public function reset(): void
    {
        $this->confirmsEnabled = false;
        $this->nacked = false;
        $this->rabbit->reset();
    }
}
