<?php

namespace App\Services;

use App\Models\Order;
use Ecommerce\Shared\Messaging\Outbox;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Checkout
{
    public function __construct(private readonly CatalogClient $catalog) {}

    /**
     * Create a pending order priced from the catalog and ask for stock.
     * The order is confirmed or cancelled later, when the catalog answers.
     *
     * @param  list<array{product_id: string, quantity: int}>  $lines
     * @return array{0: Order, 1: bool} the order, and whether it was created by this call
     */
    public function place(string $userId, array $lines, ?string $idempotencyKey = null): array
    {
        if ($idempotencyKey !== null && ($existing = $this->findByKey($userId, $idempotencyKey))) {
            return [$existing, false];
        }

        $products = $this->catalog->products(array_column($lines, 'product_id'));

        $errors = [];
        foreach ($lines as $index => $line) {
            if (! isset($products[$line['product_id']])) {
                $errors["items.$index.product_id"] = ['This product is not available.'];
            }
        }

        if ($errors === [] && count(array_unique(array_map(fn (array $p) => $p['price']['currency'], $products))) > 1) {
            $errors['items'] = ['All products in one order must use the same currency.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $items = array_map(function (array $line) use ($products): array {
            $product = $products[$line['product_id']];

            return [
                'product_id' => $product['id'],
                'name' => $product['name'],
                'sku' => $product['sku'],
                'unit_price_amount' => $product['price']['amount'],
                'quantity' => $line['quantity'],
                'line_total_amount' => $product['price']['amount'] * $line['quantity'],
            ];
        }, $lines);

        $currency = reset($products)['price']['currency'];

        try {
            $order = DB::transaction(function () use ($userId, $idempotencyKey, $items, $currency): Order {
                $order = Order::create([
                    'user_id' => $userId,
                    'status' => Order::PENDING,
                    'total_amount' => array_sum(array_column($items, 'line_total_amount')),
                    'currency' => $currency,
                    'idempotency_key' => $idempotencyKey,
                ]);
                $order->items()->createMany($items);

                Outbox::record('order.placed', [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'total_amount' => $order->total_amount,
                    'currency' => $order->currency,
                    'items' => array_map(fn (array $item) => [
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price_amount' => $item['unit_price_amount'],
                    ], $items),
                ]);

                return $order;
            });
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent request with the same idempotency key won the race.
            if ($idempotencyKey !== null && ($existing = $this->findByKey($userId, $idempotencyKey))) {
                return [$existing, false];
            }

            throw $e;
        }

        return [$order->load('items'), true];
    }

    private function findByKey(string $userId, string $key): ?Order
    {
        return Order::with('items')->where('user_id', $userId)->where('idempotency_key', $key)->first();
    }
}
