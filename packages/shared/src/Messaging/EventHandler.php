<?php

namespace Ecommerce\Shared\Messaging;

interface EventHandler
{
    /**
     * Runs inside a DB transaction together with the deduplication record,
     * so database changes made here are applied exactly once per event.
     * Throw to signal failure; the event will be retried, then dead-lettered.
     */
    public function handle(Event $event): void;
}
