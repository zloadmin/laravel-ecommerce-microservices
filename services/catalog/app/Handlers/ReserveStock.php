<?php

namespace App\Handlers;

use App\Models\Product;
use App\Models\StockReservation;
use Ecommerce\Shared\Messaging\Event;
use Ecommerce\Shared\Messaging\EventHandler;
use Ecommerce\Shared\Messaging\Outbox;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * order.placed: reserve stock for every line of the order, all or nothing,
 * and answer with stock.reserved or stock.rejected.
 */
class ReserveStock implements EventHandler
{
    public function handle(Event $event): void
    {
        $orderId = $event->payload['order_id'];

        // Already decided, or the order was cancelled before this event arrived.
        if (StockReservation::whereKey($orderId)->exists()) {
            return;
        }

        $items = collect($event->payload['items'])
            ->map(fn (array $item) => ['product_id' => $item['product_id'], 'quantity' => (int) $item['quantity']])
            ->sortBy('product_id') // consistent lock order prevents deadlocks between orders
            ->values();

        $shortProductId = null;

        try {
            // Nested transaction: a shortage undoes the lines already taken.
            DB::transaction(function () use ($items, &$shortProductId): void {
                foreach ($items as $item) {
                    $taken = Product::whereKey($item['product_id'])
                        ->where('is_active', true)
                        ->where('stock', '>=', $item['quantity'])
                        ->decrement('stock', $item['quantity']);

                    if ($taken === 0) {
                        $shortProductId = $item['product_id'];

                        throw new RuntimeException('insufficient stock');
                    }
                }
            });
        } catch (RuntimeException $e) {
            if ($shortProductId === null) {
                throw $e;
            }

            StockReservation::create(['order_id' => $orderId, 'status' => StockReservation::REJECTED, 'items' => []]);
            Outbox::record('stock.rejected', [
                'order_id' => $orderId,
                'reason' => 'insufficient_stock',
                'product_id' => $shortProductId,
            ]);

            return;
        }

        StockReservation::create(['order_id' => $orderId, 'status' => StockReservation::RESERVED, 'items' => $items->all()]);
        Outbox::record('stock.reserved', ['order_id' => $orderId]);
    }
}
