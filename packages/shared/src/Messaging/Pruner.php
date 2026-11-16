<?php

namespace Ecommerce\Shared\Messaging;

use Illuminate\Support\Facades\DB;

/**
 * Deletes messaging rows that are no longer needed: outbox events that were
 * published, and deduplication records older than any plausible redelivery.
 */
final class Pruner
{
    /** @return array{outbox: int, processed: int} rows deleted */
    public function prune(int $retentionDays): array
    {
        $cutoff = now()->subDays($retentionDays);

        return [
            // Unpublished rows are never touched, however old.
            'outbox' => DB::table('outbox_messages')->whereNotNull('published_at')->where('published_at', '<', $cutoff)->delete(),
            'processed' => DB::table('processed_messages')->where('processed_at', '<', $cutoff)->delete(),
        ];
    }
}
