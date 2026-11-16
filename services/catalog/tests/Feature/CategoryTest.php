<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Ecommerce\Shared\Testing\JwtTestKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_list_and_view_categories(): void
    {
        Category::factory()->create(['name' => 'Zebra']);
        $apple = Category::factory()->create(['name' => 'Apple']);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Apple');

        $this->getJson("/api/v1/categories/{$apple->id}")->assertOk()->assertJsonPath('data.slug', $apple->slug);
        $this->getJson('/api/v1/categories/not-a-uuid')->assertNotFound();
    }

    public function test_only_admins_can_write_categories(): void
    {
        $this->postJson('/api/v1/categories', ['name' => 'Toys'])->assertStatus(401);
        $this->withToken(JwtTestKeys::token())->postJson('/api/v1/categories', ['name' => 'Toys'])->assertStatus(403);
    }

    public function test_admin_can_create_update_and_delete_a_category(): void
    {
        $this->withToken(JwtTestKeys::token(role: 'admin'));

        $id = $this->postJson('/api/v1/categories', ['name' => 'Home Audio'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'home-audio')
            ->json('data.id');

        $this->postJson('/api/v1/categories', ['name' => 'Home Audio'])->assertStatus(422);

        $this->patchJson("/api/v1/categories/$id", ['name' => 'Audio'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Audio')
            ->assertJsonPath('data.slug', 'home-audio');

        $this->deleteJson("/api/v1/categories/$id")->assertNoContent();
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $category = Category::factory()->create();
        Product::factory()->for($category)->create();

        $this->withToken(JwtTestKeys::token(role: 'admin'))
            ->deleteJson("/api/v1/categories/{$category->id}")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'category_not_empty');
    }
}
