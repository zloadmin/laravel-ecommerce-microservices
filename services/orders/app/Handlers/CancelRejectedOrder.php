<?php

namespace App\Handlers;

use App\Models\Order;
use Ecommerce\Shared\Messaging\Event;
use Ecommerce\Shared\Messaging\EventHandler;
use Ecommerce\Shared\Messaging\Outbox;

/**
 * stock.rejected: the catalog could not reserve stock, so the order is cancelled.
 */
class CancelRejectedOrder implements EventHandler
{
    public function handle(Event $event): void
    {
        $order = Order::whereKey($event->payload['order_id'])->lockForUpdate()->first();

        if ($order === null || $order->status !== Order::PENDING) {
            return;
        }

        $reason = $event->payload['reason'] ?? 'stock_rejected';
        $order->update(['status' => Order::CANCELLED, 'cancellation_reason' => $reason]);

        Outbox::record('order.cancelled', ['order_id' => $order->id, 'user_id' => $order->user_id, 'reason' => $reason]);
    }
}
