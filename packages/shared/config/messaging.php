<?php

return [

    /*
    | A service opts in by shipping its own config/messaging.php with
    | 'enabled' => true. That also loads the outbox and inbox migrations.
    */
    'enabled' => false,

    // Name used for this service's queue and as the event source.
    'service' => env('APP_NAME', 'service'),

    'connection' => [
        'host' => env('RABBITMQ_HOST', 'rabbitmq'),
        'port' => (int) env('RABBITMQ_PORT', 5672),
        'user' => env('RABBITMQ_USER', 'ecommerce'),
        'password' => env('RABBITMQ_PASSWORD', 'secret'),
        'vhost' => env('RABBITMQ_VHOST', '/'),
    ],

    // Published outbox rows and deduplication records are deleted after this
    // many days. The outbox:relay worker prunes once an hour.
    'retention_days' => (int) env('MESSAGING_RETENTION_DAYS', 7),

    // One topic exchange carries every domain event; the routing key is the event type.
    'exchange' => 'ecommerce.events',

    /*
    | Events this service consumes: binding pattern => handler class(es).
    | Patterns use AMQP topic syntax: "*" is one word, "#" is any number of words.
    |   'order.placed' => ReserveStock::class,
    */
    'handlers' => [],

];
