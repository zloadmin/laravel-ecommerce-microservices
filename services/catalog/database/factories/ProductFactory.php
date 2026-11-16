<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(3, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'sku' => Str::upper(fake()->unique()->bothify('???-#####')),
            'description' => fake()->sentence(),
            'price_amount' => fake()->numberBetween(500, 50000),
            'currency' => 'USD',
            'is_active' => true,
            'stock' => 10,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
