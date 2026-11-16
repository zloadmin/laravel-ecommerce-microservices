<?php

namespace Ecommerce\Shared\Messaging;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Transactional outbox. Call record() inside the same DB transaction as the
 * business change: the event is then stored if and only if the change commits,
 * and the outbox:relay worker delivers it to the broker afterwards.
 */
final class Outbox
{
    /** @param  array<string, mixed>  $payload */
    public static function record(string $type, array $payload): Event
    {
        $event = new Event(
            id: (string) Str::uuid7(),
            type: $type,
            source: config('messaging.service'),
            occurredAt: now()->toIso8601String(),
            payload: $payload,
            correlationId: Context::get('correlation_id'),
        );

        DB::table('outbox_messages')->insert([
            'id' => $event->id,
            'type' => $event->type,
            'body' => $event->toJson(),
            'created_at' => now(),
        ]);

        return $event;
    }
}
