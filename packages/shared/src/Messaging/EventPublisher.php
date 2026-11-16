<?php

namespace Ecommerce\Shared\Messaging;

interface EventPublisher
{
    /** Send one event to the broker. $type is used as the routing key. */
    public function publish(string $type, string $messageId, string $body): void;

    /** Block until the broker has confirmed everything published so far; throw otherwise. */
    public function confirm(): void;
}
