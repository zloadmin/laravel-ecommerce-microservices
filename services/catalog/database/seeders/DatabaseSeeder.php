<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Local development data. Safe to run repeatedly. */
    public function run(): void
    {
        $catalog = [
            'Laptops' => [['Ultrabook 14', 'LAP-00001', 129900], ['Workstation 16', 'LAP-00002', 249900]],
            'Phones' => [['Phone One', 'PHN-00001', 69900], ['Phone One Pro', 'PHN-00002', 99900]],
            'Accessories' => [['USB-C Cable', 'ACC-00001', 1500], ['Wireless Mouse', 'ACC-00002', 3900]],
        ];

        foreach ($catalog as $categoryName => $products) {
            $category = Category::firstOrCreate(['slug' => str($categoryName)->slug()], ['name' => $categoryName]);

            foreach ($products as [$name, $sku, $price]) {
                Product::firstOrCreate(['sku' => $sku], [
                    'category_id' => $category->id,
                    'name' => $name,
                    'slug' => str($name)->slug(),
                    'description' => "$name from the $categoryName range.",
                    'price_amount' => $price,
                    'currency' => 'USD',
                    'stock' => 25,
                ]);
            }
        }
    }
}
