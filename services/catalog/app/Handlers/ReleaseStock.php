<?php

namespace App\Handlers;

use App\Models\Product;
use App\Models\StockReservation;
use Ecommerce\Shared\Messaging\Event;
use Ecommerce\Shared\Messaging\EventHandler;

/**
 * order.cancelled: put reserved stock back.
 */
class ReleaseStock implements EventHandler
{
    public function handle(Event $event): void
    {
        $orderId = $event->payload['order_id'];
        $reservation = StockReservation::whereKey($orderId)->lockForUpdate()->first();

        if ($reservation === null) {
            // The cancellation overtook order.placed. Leave a marker so that
            // ReserveStock does not reserve for an order that is already dead.
            StockReservation::create(['order_id' => $orderId, 'status' => StockReservation::RELEASED, 'items' => []]);

            return;
        }

        if ($reservation->status !== StockReservation::RESERVED) {
            return; // rejected or already released: nothing is held
        }

        foreach ($reservation->items as $item) {
            Product::whereKey($item['product_id'])->increment('stock', $item['quantity']);
        }

        $reservation->update(['status' => StockReservation::RELEASED]);
    }
}
