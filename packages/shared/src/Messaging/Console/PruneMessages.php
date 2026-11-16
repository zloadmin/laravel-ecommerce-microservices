<?php

namespace Ecommerce\Shared\Messaging\Console;

use Ecommerce\Shared\Messaging\Pruner;
use Illuminate\Console\Command;

class PruneMessages extends Command
{
    protected $signature = 'messaging:prune {--days= : Retention in days (default: messaging.retention_days)}';

    protected $description = 'Delete published outbox events and old deduplication records';

    public function handle(Pruner $pruner): int
    {
        $days = (int) ($this->option('days') ?? config('messaging.retention_days'));
        $deleted = $pruner->prune($days);

        $this->components->info("Pruned {$deleted['outbox']} outbox and {$deleted['processed']} processed rows older than $days days.");

        return self::SUCCESS;
    }
}
