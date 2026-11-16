<?php

namespace Tests\Feature;

use Ecommerce\Shared\Testing\JwtTestKeys;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CartTest extends TestCase
{
    private const GUEST = '0193a1b2-0000-7000-8000-0000000000c1';

    private const USER = '0193a1b2-0000-7000-8000-0000000000a1';

    private const KETTLE = '0193a1b2-0000-7000-8000-0000000000b1';

    private const MUG = '0193a1b2-0000-7000-8000-0000000000b2';

    private const GONE = '0193a1b2-0000-7000-8000-0000000000b9';

    /** @var array<string, array<string, mixed>> products the fake catalog knows */
    private array $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalog = [
            self::KETTLE => ['id' => self::KETTLE, 'name' => 'Kettle', 'slug' => 'kettle', 'sku' => 'KET-1', 'stock' => 2, 'price' => ['amount' => 4000, 'currency' => 'USD']],
            self::MUG => ['id' => self::MUG, 'name' => 'Mug', 'slug' => 'mug', 'sku' => 'MUG-1', 'stock' => 50, 'price' => ['amount' => 900, 'currency' => 'USD']],
        ];

        Http::fake(['catalog:8080/*' => function (Request $request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

            return Http::response(['data' => array_values(array_intersect_key($this->catalog, array_flip($query['ids'] ?? [])))]);
        }]);
    }

    public function test_a_cart_needs_an_owner(): void
    {
        $this->getJson('/api/v1/cart')->assertStatus(400)->assertJsonPath('error.code', 'bad_request');
        $this->getJson('/api/v1/cart', ['X-Cart-Id' => 'not-a-uuid'])->assertStatus(400);
        $this->withToken('garbage')->getJson('/api/v1/cart', ['X-Cart-Id' => self::GUEST])->assertStatus(401);
    }

    public function test_guest_builds_a_cart_priced_from_the_catalog(): void
    {
        $this->guest()->getJson('/api/v1/cart')
            ->assertOk()
            ->assertExactJson(['data' => ['items' => [], 'item_count' => 0, 'total' => null]]);

        $this->postJson('/api/v1/cart/items', ['product_id' => self::MUG])->assertOk()->assertJsonPath('data.item_count', 1);
        $this->postJson('/api/v1/cart/items', ['product_id' => self::MUG, 'quantity' => 2])->assertOk();
        $this->postJson('/api/v1/cart/items', ['product_id' => self::KETTLE, 'quantity' => 3, 'unit_price' => 1])->assertOk();

        $this->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.item_count', 6)
            ->assertJsonPath('data.total', ['amount' => 14700, 'currency' => 'USD'])
            ->assertJsonPath('data.items.0', [
                'product_id' => self::MUG, 'quantity' => 3, 'available' => true, 'in_stock' => true,
                'name' => 'Mug', 'slug' => 'mug', 'sku' => 'MUG-1',
                'unit_price' => ['amount' => 900, 'currency' => 'USD'],
                'line_total' => ['amount' => 2700, 'currency' => 'USD'],
            ])
            ->assertJsonPath('data.items.1.in_stock', false); // 3 kettles wanted, 2 in stock
    }

    public function test_quantity_can_be_set_and_lines_removed(): void
    {
        $this->guest()->postJson('/api/v1/cart/items', ['product_id' => self::MUG, 'quantity' => 5]);
        $this->postJson('/api/v1/cart/items', ['product_id' => self::KETTLE]);

        $this->putJson('/api/v1/cart/items/'.self::MUG, ['quantity' => 2])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 2);

        $this->deleteJson('/api/v1/cart/items/'.self::MUG)
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.product_id', self::KETTLE);

        $this->deleteJson('/api/v1/cart')->assertOk()->assertJsonPath('data.items', []);
        $this->getJson('/api/v1/cart')->assertJsonPath('data.item_count', 0);
    }

    public function test_input_is_validated(): void
    {
        $this->guest();

        $this->postJson('/api/v1/cart/items', ['product_id' => self::GONE])
            ->assertStatus(422)
            ->assertJsonPath('error.details.fields.product_id.0', 'This product is not available.');
        $this->postJson('/api/v1/cart/items', ['product_id' => 'nope'])->assertStatus(422);
        $this->postJson('/api/v1/cart/items', ['product_id' => self::MUG, 'quantity' => 0])->assertStatus(422);
        $this->putJson('/api/v1/cart/items/'.self::MUG, ['quantity' => 101])->assertStatus(422);
        $this->putJson('/api/v1/cart/items/not-a-uuid', ['quantity' => 1])->assertStatus(422);

        // Adding beyond the cap stops at the cap.
        $this->postJson('/api/v1/cart/items', ['product_id' => self::MUG, 'quantity' => 80]);
        $this->postJson('/api/v1/cart/items', ['product_id' => self::MUG, 'quantity' => 80])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 100);
    }

    public function test_product_removed_from_the_catalog_is_flagged_and_not_charged(): void
    {
        $this->guest()->postJson('/api/v1/cart/items', ['product_id' => self::MUG]);
        $this->postJson('/api/v1/cart/items', ['product_id' => self::KETTLE]);

        unset($this->catalog[self::KETTLE]);

        $this->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.items.1', ['product_id' => self::KETTLE, 'quantity' => 1, 'available' => false])
            ->assertJsonPath('data.total.amount', 900);
    }

    public function test_carts_are_separate_per_owner(): void
    {
        $this->guest()->postJson('/api/v1/cart/items', ['product_id' => self::MUG]);

        $this->flushHeaders()->customer()->getJson('/api/v1/cart')->assertJsonPath('data.item_count', 0);
        $this->flushHeaders()->getJson('/api/v1/cart', ['X-Cart-Id' => '0193a1b2-0000-7000-8000-0000000000c2'])
            ->assertJsonPath('data.item_count', 0);
        // The token wins over X-Cart-Id.
        $this->flushHeaders()->customer()->getJson('/api/v1/cart', ['X-Cart-Id' => self::GUEST])->assertJsonPath('data.item_count', 0);
    }

    public function test_guest_cart_is_merged_into_the_customer_cart_after_login(): void
    {
        $this->guest()->postJson('/api/v1/cart/items', ['product_id' => self::MUG, 'quantity' => 2]);
        $this->postJson('/api/v1/cart/items', ['product_id' => self::KETTLE]);

        $this->flushHeaders()->customer()->postJson('/api/v1/cart/items', ['product_id' => self::MUG, 'quantity' => 3]);

        $this->postJson('/api/v1/cart/merge', ['guest_cart_id' => self::GUEST])
            ->assertOk()
            ->assertJsonPath('data.item_count', 6)
            ->assertJsonPath('data.items.0.quantity', 5);

        $this->flushHeaders()->guest()->getJson('/api/v1/cart')->assertJsonPath('data.item_count', 0);
        $this->flushHeaders()->guest()->postJson('/api/v1/cart/merge', ['guest_cart_id' => self::GUEST])->assertStatus(401);
    }

    public function test_checkout_sends_the_cart_to_orders_and_empties_it(): void
    {
        Http::fake(['orders:8080/*' => Http::response(['data' => ['id' => 'order-1', 'status' => 'pending']], 201)]);

        $token = JwtTestKeys::token(self::USER);
        $this->withToken($token)->postJson('/api/v1/cart/items', ['product_id' => self::MUG, 'quantity' => 2]);
        $this->postJson('/api/v1/cart/items', ['product_id' => self::KETTLE]);

        $this->postJson('/api/v1/cart/checkout', [], ['Idempotency-Key' => 'checkout-0001', 'X-Correlation-ID' => 'corr-cart-1'])
            ->assertCreated()
            ->assertJsonPath('data.id', 'order-1');

        Http::assertSent(fn (Request $request) => $request->url() === 'http://orders:8080/api/v1/orders'
            && $request->hasHeader('Authorization', "Bearer $token")
            && $request->hasHeader('Idempotency-Key', 'checkout-0001')
            && $request->hasHeader('X-Correlation-ID', 'corr-cart-1')
            && $request->data() === ['items' => [
                ['product_id' => self::MUG, 'quantity' => 2],
                ['product_id' => self::KETTLE, 'quantity' => 1],
            ]]);

        $this->getJson('/api/v1/cart')->assertJsonPath('data.item_count', 0);
    }

    public function test_rejected_or_failed_checkout_keeps_the_cart(): void
    {
        $this->customer()->postJson('/api/v1/cart/items', ['product_id' => self::MUG]);

        Http::fake(['orders:8080/*' => Http::sequence()
            ->push(['error' => ['code' => 'validation_failed', 'message' => 'This product is not available.']], 422)
            ->push('boom', 500)]);

        $this->postJson('/api/v1/cart/checkout')->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        $this->postJson('/api/v1/cart/checkout')->assertStatus(503)->assertJsonPath('error.code', 'service_unavailable');

        $this->getJson('/api/v1/cart')->assertJsonPath('data.item_count', 1);
    }

    public function test_checkout_requires_a_customer_and_a_non_empty_cart(): void
    {
        $this->guest()->postJson('/api/v1/cart/checkout')->assertStatus(401);
        $this->flushHeaders()->customer()->postJson('/api/v1/cart/checkout')->assertStatus(422)->assertJsonPath('error.code', 'cart_empty');
    }

    public function test_unavailable_catalog_is_reported(): void
    {
        Http::swap(new Factory);
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->guest()->postJson('/api/v1/cart/items', ['product_id' => self::MUG])
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'service_unavailable');
    }

    private function guest(): static
    {
        return $this->withHeaders(['X-Cart-Id' => self::GUEST]);
    }

    private function customer(): static
    {
        return $this->withToken(JwtTestKeys::token(self::USER));
    }
}
