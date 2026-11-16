<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Ecommerce\Shared\Messaging\Event;
use Ecommerce\Shared\Testing\JwtTestKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_list_only_active_products_with_their_category(): void
    {
        $category = Category::factory()->create();
        $active = Product::factory()->for($category)->create();
        Product::factory()->inactive()->create();

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('data.0.price', ['amount' => $active->price_amount, 'currency' => 'USD'])
            ->assertJsonPath('data.0.category.slug', $category->slug);
    }

    public function test_admins_also_see_inactive_products(): void
    {
        Product::factory()->create();
        $inactive = Product::factory()->inactive()->create();

        $this->withToken(JwtTestKeys::token(role: 'admin'))->getJson('/api/v1/products')->assertJsonCount(2, 'data');
        $this->withToken(JwtTestKeys::token(role: 'admin'))->getJson("/api/v1/products/{$inactive->id}")->assertOk();

        $this->withToken(JwtTestKeys::token())->getJson('/api/v1/products')->assertJsonCount(1, 'data');
        $this->flushHeaders()->getJson("/api/v1/products/{$inactive->id}")->assertNotFound();
    }

    public function test_products_can_be_filtered_by_search_category_price_and_ids(): void
    {
        $phones = Category::factory()->create(['slug' => 'phones']);
        $cheap = Product::factory()->for($phones)->create(['name' => 'Budget Phone', 'price_amount' => 10000]);
        $pricey = Product::factory()->for($phones)->create(['name' => 'Flagship Phone', 'sku' => 'FLG-1', 'price_amount' => 90000]);
        $other = Product::factory()->create(['name' => 'Desk Lamp', 'price_amount' => 3000]);

        $ids = fn (string $query) => collect($this->getJson("/api/v1/products?$query")->assertOk()->json('data'))
            ->pluck('id')->sort()->values()->all();
        $sorted = fn (Product ...$products) => collect($products)->pluck('id')->sort()->values()->all();

        $this->assertSame($sorted($cheap, $pricey), $ids('q=phone'));
        $this->assertSame($sorted($pricey), $ids('q=flg'));
        $this->assertSame($sorted($cheap, $pricey), $ids('category=phones'));
        $this->assertSame($sorted($cheap, $pricey), $ids("category={$phones->id}"));
        $this->assertSame([], $ids('category=missing'));
        $this->assertSame($sorted($cheap), $ids('min_price=5000&max_price=20000'));
        $this->assertSame($sorted($cheap, $other), $ids("ids[]={$cheap->id}&ids[]={$other->id}"));
    }

    public function test_products_are_sorted_and_cursor_paginated(): void
    {
        foreach ([300, 100, 200, 100] as $price) {
            Product::factory()->create(['price_amount' => $price]);
        }

        $first = $this->getJson('/api/v1/products?sort=price&per_page=3')->assertOk();
        $this->assertSame([100, 100, 200], collect($first->json('data'))->pluck('price.amount')->all());

        $cursor = $first->json('meta.next_cursor');
        $this->assertNotNull($cursor);
        $this->assertStringContainsString('sort=price', $first->json('links.next'));

        // Behind the gateway, links must use the public address.
        $forwarded = ['X-Forwarded-Host' => 'shop.example.com', 'X-Forwarded-Port' => '443', 'X-Forwarded-Proto' => 'https'];
        $this->assertStringStartsWith(
            'https://shop.example.com/api/v1/products?',
            $this->getJson('/api/v1/products?per_page=3', $forwarded)->json('links.next'),
        );

        $second = $this->getJson("/api/v1/products?sort=price&per_page=3&cursor=$cursor")->assertOk();
        $this->assertSame([300], collect($second->json('data'))->pluck('price.amount')->all());
        $this->assertNull($second->json('meta.next_cursor'));

        $this->getJson('/api/v1/products?sort=-price')->assertJsonPath('data.0.price.amount', 300);
        $this->getJson('/api/v1/products?sort=rating')->assertStatus(422);
        $this->getJson('/api/v1/products?per_page=1000')->assertStatus(422);
    }

    public function test_product_can_be_fetched_by_id_or_slug(): void
    {
        $product = Product::factory()->create(['slug' => 'blue-kettle']);

        $this->getJson("/api/v1/products/{$product->id}")->assertOk()->assertJsonPath('data.slug', 'blue-kettle');
        $this->getJson('/api/v1/products/blue-kettle')->assertOk()->assertJsonPath('data.id', $product->id);
        $this->getJson('/api/v1/products/nope')->assertNotFound()->assertJsonPath('error.code', 'not_found');
    }

    public function test_only_admins_can_write_products(): void
    {
        $product = Product::factory()->create();
        $payload = ['name' => 'Thing', 'sku' => 'THG-1', 'price_amount' => 100];

        $this->postJson('/api/v1/products', $payload)->assertStatus(401);

        $this->withToken(JwtTestKeys::token());
        $this->postJson('/api/v1/products', $payload)->assertStatus(403)->assertJsonPath('error.code', 'forbidden');
        $this->patchJson("/api/v1/products/{$product->id}", ['name' => 'Hacked'])->assertStatus(403);
        $this->deleteJson("/api/v1/products/{$product->id}")->assertStatus(403);

        $this->assertDatabaseCount('products', 1);
    }

    public function test_admin_can_create_a_product_with_defaults(): void
    {
        $category = Category::factory()->create();

        $this->withToken(JwtTestKeys::token(role: 'admin'))
            ->postJson('/api/v1/products', [
                'name' => 'Mechanical Keyboard',
                'sku' => 'KEY-00001',
                'price_amount' => 8900,
                'category_id' => $category->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'mechanical-keyboard')
            ->assertJsonPath('data.price', ['amount' => 8900, 'currency' => 'USD'])
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.category.id', $category->id);
    }

    public function test_product_input_is_validated(): void
    {
        Product::factory()->create(['sku' => 'DUP-1', 'slug' => 'taken']);

        $this->withToken(JwtTestKeys::token(role: 'admin'))
            ->postJson('/api/v1/products', [
                'name' => 'Taken',
                'sku' => 'DUP-1',
                'price_amount' => 19.99,
                'currency' => 'dollars',
                'category_id' => '01900000-0000-7000-8000-000000000000',
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['slug', 'sku', 'price_amount', 'currency', 'category_id']]]]);
    }

    public function test_admin_can_update_and_delete_a_product(): void
    {
        $product = Product::factory()->create(['sku' => 'KEEP-1']);
        $this->withToken(JwtTestKeys::token(role: 'admin'));

        $this->patchJson("/api/v1/products/{$product->id}", ['price_amount' => 1234, 'is_active' => false, 'sku' => 'KEEP-1'])
            ->assertOk()
            ->assertJsonPath('data.price.amount', 1234)
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.name', $product->name);

        $this->deleteJson("/api/v1/products/{$product->id}")->assertNoContent();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_product_changes_are_recorded_in_the_outbox(): void
    {
        $this->withToken(JwtTestKeys::token(role: 'admin'));

        $id = $this->withHeaders(['X-Correlation-ID' => 'corr-catalog-1'])
            ->postJson('/api/v1/products', ['name' => 'Lamp', 'sku' => 'LMP-1', 'price_amount' => 2500])
            ->json('data.id');
        $this->patchJson("/api/v1/products/$id", ['price_amount' => 2000])->assertOk();
        $this->patchJson("/api/v1/products/$id", ['price_amount' => 2000])->assertOk(); // no change, no event
        $this->deleteJson("/api/v1/products/$id")->assertNoContent();

        $events = DB::table('outbox_messages')->orderBy('id')->pluck('body')->map(fn (string $body) => Event::fromJson($body));

        $this->assertSame(['product.created', 'product.updated', 'product.deleted'], $events->pluck('type')->all());
        $this->assertSame('catalog', $events[0]->source);
        $this->assertSame('corr-catalog-1', $events[0]->correlationId);
        $this->assertSame(
            ['id' => $id, 'category_id' => null, 'name' => 'Lamp', 'slug' => 'lamp', 'sku' => 'LMP-1', 'price_amount' => 2500, 'currency' => 'USD', 'is_active' => true],
            $events[0]->payload,
        );
        $this->assertSame(2000, $events[1]->payload['price_amount']);
        $this->assertSame(['id' => $id], $events[2]->payload);
    }

    public function test_rejected_writes_record_no_event(): void
    {
        $this->withToken(JwtTestKeys::token(role: 'admin'))
            ->postJson('/api/v1/products', ['name' => 'No price', 'sku' => 'NOP-1'])
            ->assertStatus(422);

        $this->assertDatabaseCount('outbox_messages', 0);
    }
}
