<?php

namespace Tests\Feature;

use App\Models\Order;
use Ecommerce\Shared\Messaging\Event;
use Ecommerce\Shared\Messaging\EventConsumer;
use Ecommerce\Shared\Testing\JwtTestKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private const USER = '0193a1b2-0000-7000-8000-0000000000a1';

    private const KETTLE = '0193a1b2-0000-7000-8000-0000000000b1';

    private const MUG = '0193a1b2-0000-7000-8000-0000000000b2';

    protected function setUp(): void
    {
        parent::setUp();

        // The catalog answers with the requested products it knows about.
        Http::fake(['catalog:8080/*' => function (Request $request) {
            $known = [
                self::KETTLE => ['id' => self::KETTLE, 'name' => 'Kettle', 'sku' => 'KET-1', 'price' => ['amount' => 4000, 'currency' => 'USD']],
                self::MUG => ['id' => self::MUG, 'name' => 'Mug', 'sku' => 'MUG-1', 'price' => ['amount' => 900, 'currency' => 'USD']],
            ];
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

            return Http::response(['data' => array_values(array_intersect_key($known, array_flip($query['ids'] ?? [])))]);
        }]);
    }

    public function test_orders_require_authentication(): void
    {
        $this->getJson('/api/v1/orders')->assertStatus(401);
        $this->postJson('/api/v1/orders', [])->assertStatus(401);
    }

    public function test_checkout_creates_a_pending_order_priced_by_the_catalog(): void
    {
        $response = $this->as(self::USER)
            ->withHeaders(['X-Correlation-ID' => 'corr-order-1'])
            ->postJson('/api/v1/orders', ['items' => [
                ['product_id' => self::KETTLE, 'quantity' => 2, 'unit_price_amount' => 1], // client prices are ignored
                ['product_id' => self::MUG, 'quantity' => 3],
            ]])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.user_id', self::USER)
            ->assertJsonPath('data.total', ['amount' => 10700, 'currency' => 'USD'])
            ->assertJsonPath('data.items.0.name', 'Kettle')
            ->assertJsonPath('data.items.0.line_total.amount', 8000)
            ->assertJsonPath('data.items.1.sku', 'MUG-1');

        Http::assertSent(fn (Request $request) => $request->hasHeader('X-Correlation-ID', 'corr-order-1'));

        [$event] = $this->published();
        $this->assertSame('order.placed', $event->type);
        $this->assertSame('corr-order-1', $event->correlationId);
        $this->assertSame([
            'order_id' => $response->json('data.id'),
            'user_id' => self::USER,
            'total_amount' => 10700,
            'currency' => 'USD',
            'items' => [
                ['product_id' => self::KETTLE, 'quantity' => 2, 'unit_price_amount' => 4000],
                ['product_id' => self::MUG, 'quantity' => 3, 'unit_price_amount' => 900],
            ],
        ], $event->payload);
    }

    public function test_checkout_validates_input_and_product_availability(): void
    {
        $this->as(self::USER);

        $this->postJson('/api/v1/orders', ['items' => []])->assertStatus(422);
        $this->postJson('/api/v1/orders', ['items' => [['product_id' => self::MUG, 'quantity' => 0]]])->assertStatus(422);
        $this->postJson('/api/v1/orders', ['items' => [
            ['product_id' => self::MUG, 'quantity' => 1],
            ['product_id' => self::MUG, 'quantity' => 2],
        ]])->assertStatus(422);

        $this->postJson('/api/v1/orders', ['items' => [
            ['product_id' => self::MUG, 'quantity' => 1],
            ['product_id' => (string) Str::uuid7(), 'quantity' => 1],
        ]])->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['fields' => ['items.1.product_id']]]]);

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame([], $this->published());
    }

    public function test_checkout_reports_an_unavailable_catalog(): void
    {
        Http::swap(new Factory);
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->as(self::USER)
            ->postJson('/api/v1/orders', ['items' => [['product_id' => self::MUG, 'quantity' => 1]]])
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'service_unavailable');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_idempotency_key_replays_the_original_order(): void
    {
        $this->as(self::USER)->withHeaders(['Idempotency-Key' => 'checkout-attempt-1']);
        $body = ['items' => [['product_id' => self::MUG, 'quantity' => 1]]];

        $id = $this->postJson('/api/v1/orders', $body)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/orders', $body)->assertOk()->assertJsonPath('data.id', $id);

        $this->assertDatabaseCount('orders', 1);
        $this->assertCount(1, $this->published());

        // The key is scoped to the user.
        $this->as((string) Str::uuid7())->postJson('/api/v1/orders', $body)->assertCreated();
        $this->withHeaders(['Idempotency-Key' => 'bad key!'])->postJson('/api/v1/orders', $body)->assertStatus(400);
    }

    public function test_customers_see_only_their_own_orders_and_admins_see_all(): void
    {
        $mine = $this->placeOrder(self::USER);
        $other = (string) Str::uuid7();
        $theirs = $this->placeOrder($other);

        $this->as(self::USER);
        $this->getJson('/api/v1/orders')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine);
        $this->getJson("/api/v1/orders/$mine")->assertOk()->assertJsonPath('data.items.0.name', 'Mug');
        $this->getJson("/api/v1/orders/$theirs")->assertNotFound();
        $this->postJson("/api/v1/orders/$theirs/cancel")->assertNotFound();
        $this->getJson("/api/v1/orders?user_id=$other")->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine);

        $this->as(role: 'admin');
        $this->getJson('/api/v1/orders')->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/orders?user_id=$other")->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $theirs);
        $this->getJson("/api/v1/orders/$theirs")->assertOk();
    }

    public function test_stock_reserved_confirms_the_order(): void
    {
        $id = $this->placeOrder(self::USER);

        $this->deliver('stock.reserved', ['order_id' => $id]);

        $this->as(self::USER)->getJson("/api/v1/orders/$id")->assertJsonPath('data.status', 'confirmed');
        $this->assertSame(['order.placed', 'order.confirmed'], array_column($this->published(), 'type'));
    }

    public function test_stock_rejected_cancels_the_order_with_the_reason(): void
    {
        $id = $this->placeOrder(self::USER);

        $this->deliver('stock.rejected', ['order_id' => $id, 'reason' => 'insufficient_stock', 'product_id' => self::MUG]);

        $this->as(self::USER)->getJson("/api/v1/orders/$id")
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'insufficient_stock');
        $this->assertSame(['order.placed', 'order.cancelled'], array_column($this->published(), 'type'));
    }

    public function test_customer_can_cancel_and_cancelled_orders_stay_cancelled(): void
    {
        $id = $this->placeOrder(self::USER);
        $this->deliver('stock.reserved', ['order_id' => $id]);

        $this->as(self::USER)->postJson("/api/v1/orders/$id/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'cancelled_by_customer');

        $this->postJson("/api/v1/orders/$id/cancel")->assertStatus(409)->assertJsonPath('error.code', 'order_not_cancellable');

        $last = collect($this->published())->last();
        $this->assertSame('order.cancelled', $last->type);
        $this->assertSame($id, $last->payload['order_id']);
    }

    public function test_late_stock_answer_does_not_revive_a_cancelled_order(): void
    {
        $id = $this->placeOrder(self::USER);
        $this->as(self::USER)->postJson("/api/v1/orders/$id/cancel")->assertOk();

        $this->deliver('stock.reserved', ['order_id' => $id]);

        $this->assertSame(Order::CANCELLED, Order::find($id)->status);
        $this->assertNotContains('order.confirmed', array_column($this->published(), 'type'));
    }

    private function as(?string $userId = null, string $role = 'customer'): static
    {
        return $this->withToken(JwtTestKeys::token($userId, $role));
    }

    private function placeOrder(string $userId): string
    {
        return $this->as($userId)
            ->postJson('/api/v1/orders', ['items' => [['product_id' => self::MUG, 'quantity' => 1]]])
            ->assertCreated()
            ->json('data.id');
    }

    /** @param  array<string, mixed>  $payload */
    private function deliver(string $type, array $payload): void
    {
        $this->app->make(EventConsumer::class)->process(
            new Event((string) Str::uuid7(), $type, 'catalog', now()->toIso8601String(), $payload),
        );
    }

    /** @return list<Event> */
    private function published(): array
    {
        return DB::table('outbox_messages')->orderBy('id')->pluck('body')
            ->map(fn (string $body) => Event::fromJson($body))->all();
    }
}
