<?php

namespace Ecommerce\Shared\Messaging;

use InvalidArgumentException;
use JsonException;

/**
 * The envelope every domain event travels in.
 */
final readonly class Event
{
    /** @param  array<string, mixed>  $payload */
    public function __construct(
        public string $id,
        public string $type,
        public string $source,
        public string $occurredAt,
        public array $payload,
        public ?string $correlationId = null,
    ) {}

    public function toJson(): string
    {
        return json_encode([
            'id' => $this->id,
            'type' => $this->type,
            'source' => $this->source,
            'occurred_at' => $this->occurredAt,
            'correlation_id' => $this->correlationId,
            'payload' => $this->payload,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @throws InvalidArgumentException when the message is not a valid envelope */
    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Event is not valid JSON.', previous: $e);
        }

        foreach (['id', 'type', 'source', 'occurred_at'] as $field) {
            if (! is_array($data) || ! is_string($data[$field] ?? null) || $data[$field] === '') {
                throw new InvalidArgumentException("Event is missing the \"$field\" field.");
            }
        }

        if (! is_array($data['payload'] ?? null)) {
            throw new InvalidArgumentException('Event is missing the "payload" field.');
        }

        return new self(
            $data['id'],
            $data['type'],
            $data['source'],
            $data['occurred_at'],
            $data['payload'],
            is_string($data['correlation_id'] ?? null) ? $data['correlation_id'] : null,
        );
    }
}
