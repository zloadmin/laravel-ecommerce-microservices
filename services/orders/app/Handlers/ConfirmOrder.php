<?php

namespace App\Handlers;

use App\Models\Order;
use Ecommerce\Shared\Messaging\Event;
use Ecommerce\Shared\Messaging\EventHandler;
use Ecommerce\Shared\Messaging\Outbox;

/**
 * stock.reserved: the catalog holds stock for the order, so it is confirmed.
 */
class ConfirmOrder implements EventHandler
{
    public function handle(Event $event): void
    {
        $order = Order::whereKey($event->payload['order_id'])->lockForUpdate()->first();

        // Not pending means the customer cancelled while stock was being reserved;
        // the catalog releases that stock when it sees order.cancelled.
        if ($order === null || $order->status !== Order::PENDING) {
            return;
        }

        $order->update(['status' => Order::CONFIRMED]);

        Outbox::record('order.confirmed', ['order_id' => $order->id, 'user_id' => $order->user_id]);
    }
}
