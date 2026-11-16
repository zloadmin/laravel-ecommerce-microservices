<?php

namespace Ecommerce\Shared\Messaging;

use Illuminate\Support\Facades\DB;

/**
 * Moves pending outbox rows to the broker. Delivery is at-least-once: if the
 * process dies after publishing but before the rows are marked, they are sent
 * again, which is why every consumer deduplicates by event id.
 */
final class OutboxRelay
{
    public function __construct(private readonly EventPublisher $publisher) {}

    /** @return int number of events published */
    public function flush(int $batchSize = 100): int
    {
        return DB::transaction(function () use ($batchSize): int {
            $query = DB::table('outbox_messages')
                ->whereNull('published_at')
                ->orderBy('id') // UUIDv7 ids sort by creation time
                ->limit($batchSize);

            // Lets several relay workers run side by side without double-sending.
            if (DB::getDriverName() === 'pgsql') {
                $query->lock('for update skip locked');
            }

            $rows = $query->get(['id', 'type', 'body']);

            if ($rows->isEmpty()) {
                return 0;
            }

            foreach ($rows as $row) {
                $this->publisher->publish($row->type, $row->id, $row->body);
            }

            $this->publisher->confirm();

            DB::table('outbox_messages')->whereIn('id', $rows->pluck('id'))->update(['published_at' => now()]);

            return $rows->count();
        });
    }
}
