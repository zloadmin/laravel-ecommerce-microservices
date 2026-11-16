<?php

namespace Ecommerce\Shared\Messaging\Console;

use Ecommerce\Shared\Messaging\EventPublisher;
use Ecommerce\Shared\Messaging\OutboxRelay;
use Ecommerce\Shared\Messaging\Pruner;
use Ecommerce\Shared\Messaging\RabbitMqPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RelayOutbox extends Command
{
    protected $signature = 'outbox:relay
        {--once : Publish what is pending and exit}
        {--sleep=1 : Seconds to wait when the outbox is empty}';

    protected $description = 'Publish pending outbox events to RabbitMQ';

    public function handle(OutboxRelay $relay, EventPublisher $publisher, Pruner $pruner): int
    {
        $running = true;
        $this->trap([SIGTERM, SIGINT], function () use (&$running): void {
            $running = false;
        });

        $this->components->info('Outbox relay started.');

        $nextPruneAt = 0;

        while ($running) {
            try {
                if (time() >= $nextPruneAt) {
                    $deleted = $pruner->prune((int) config('messaging.retention_days'));
                    $nextPruneAt = time() + 3600;

                    if (array_sum($deleted) > 0) {
                        Log::info('Old messaging rows pruned.', $deleted);
                    }
                }

                $published = $relay->flush();
            } catch (Throwable $e) {
                Log::error('Outbox relay failed; will retry.', ['error' => $e->getMessage()]);

                if ($this->option('once')) {
                    throw $e;
                }

                // Start from a clean slate: the broker or the database may have gone away.
                if ($publisher instanceof RabbitMqPublisher) {
                    $publisher->reset();
                }
                DB::disconnect();
                sleep(3);

                continue;
            }

            if ($published > 0) {
                Log::info('Outbox events published.', ['count' => $published]);

                continue; // there may be more; do not sleep
            }

            if ($this->option('once')) {
                break;
            }

            sleep(max(1, (int) $this->option('sleep')));
        }

        return self::SUCCESS;
    }
}
