<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockReservation;
use Ecommerce\Shared\Messaging\Event;
use Ecommerce\Shared\Messaging\EventConsumer;
use Ecommerce\Shared\Testing\JwtTestKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase;

    private const ORDER = '0193a1b2-0000-7000-8000-00000000000a';

    public function test_order_placed_reserves_stock_and_announces_it(): void
    {
        $a = Product::factory()->create(['stock' => 5]);
        $b = Product::factory()->create(['stock' => 2]);

        $this->deliver('order.placed', $this->order([[$a, 3], [$b, 2]]));

        $this->assertSame(2, $a->refresh()->stock);
        $this->assertSame(0, $b->refresh()->stock);
        $this->assertSame(StockReservation::RESERVED, StockReservation::find(self::ORDER)->status);
        $this->assertSame([['stock.reserved', ['order_id' => self::ORDER]]], $this->published());
    }

    public function test_shortage_on_any_line_rejects_the_whole_order_and_takes_nothing(): void
    {
        $plenty = Product::factory()->create(['stock' => 50]);
        $scarce = Product::factory()->create(['stock' => 1]);

        $this->deliver('order.placed', $this->order([[$plenty, 5], [$scarce, 2]]));

        $this->assertSame(50, $plenty->refresh()->stock);
        $this->assertSame(1, $scarce->refresh()->stock);
        $this->assertSame([['stock.rejected', [
            'order_id' => self::ORDER,
            'reason' => 'insufficient_stock',
            'product_id' => $scarce->id,
        ]]], $this->published());
    }

    public function test_inactive_and_unknown_products_are_rejected(): void
    {
        $inactive = Product::factory()->inactive()->create(['stock' => 10]);

        $this->deliver('order.placed', $this->order([[$inactive, 1]]));
        $this->assertSame('stock.rejected', $this->published()[0][0]);
        $this->assertSame(10, $inactive->refresh()->stock);
    }

    public function test_order_cancelled_puts_reserved_stock_back_once(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $this->deliver('order.placed', $this->order([[$product, 4]]));

        $this->deliver('order.cancelled', ['order_id' => self::ORDER]);
        $this->deliver('order.cancelled', ['order_id' => self::ORDER]); // a second cancellation event

        $this->assertSame(5, $product->refresh()->stock);
        $this->assertSame(StockReservation::RELEASED, StockReservation::find(self::ORDER)->status);
    }

    public function test_cancelling_a_rejected_order_changes_nothing(): void
    {
        $product = Product::factory()->create(['stock' => 1]);
        $this->deliver('order.placed', $this->order([[$product, 9]]));

        $this->deliver('order.cancelled', ['order_id' => self::ORDER]);

        $this->assertSame(1, $product->refresh()->stock);
        $this->assertSame(StockReservation::REJECTED, StockReservation::find(self::ORDER)->status);
    }

    public function test_cancellation_that_arrives_first_blocks_the_late_reservation(): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        $this->deliver('order.cancelled', ['order_id' => self::ORDER]);
        $this->deliver('order.placed', $this->order([[$product, 4]]));

        $this->assertSame(5, $product->refresh()->stock);
        $this->assertSame([], $this->published());
    }

    public function test_admin_can_set_stock_and_it_is_exposed(): void
    {
        $product = Product::factory()->create(['stock' => 0]);

        $this->withToken(JwtTestKeys::token(role: 'admin'))
            ->patchJson("/api/v1/products/{$product->id}", ['stock' => 40])
            ->assertOk()
            ->assertJsonPath('data.stock', 40);

        $this->getJson("/api/v1/products/{$product->id}")->assertJsonPath('data.stock', 40);
    }

    /**
     * @param  list<array{Product, int}>  $lines
     * @return array<string, mixed>
     */
    private function order(array $lines): array
    {
        return [
            'order_id' => self::ORDER,
            'items' => array_map(fn (array $line) => ['product_id' => $line[0]->id, 'quantity' => $line[1]], $lines),
        ];
    }

    /** @param  array<string, mixed>  $payload */
    private function deliver(string $type, array $payload): void
    {
        $this->app->make(EventConsumer::class)->process(
            new Event((string) Str::uuid7(), $type, 'orders', now()->toIso8601String(), $payload),
        );
    }

    /** @return list<array{string, array<string, mixed>}> */
    private function published(): array
    {
        return DB::table('outbox_messages')->orderBy('id')->pluck('body')
            ->map(fn (string $body) => Event::fromJson($body))
            ->reject(fn (Event $event) => str_starts_with($event->type, 'product.'))
            ->map(fn (Event $event) => [$event->type, $event->payload])
            ->values()->all();
    }
}
