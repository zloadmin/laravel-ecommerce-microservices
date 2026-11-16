<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Carts are small maps of product id => quantity, kept in the cache store
 * (Redis in every real environment) with a sliding expiry.
 */
class CartStore
{
    /** @return array<string, int> */
    public function get(string $owner): array
    {
        return Cache::get($this->key($owner), []);
    }

    /**
     * Change a cart under a lock, so concurrent requests cannot lose updates.
     *
     * @param  Closure(array<string, int>): array<string, int>  $change
     * @return array<string, int> the cart after the change
     */
    public function update(string $owner, Closure $change): array
    {
        return Cache::lock($this->key($owner).':lock', 5)->block(3, function () use ($owner, $change): array {
            $items = $change($this->get($owner));

            if ($items === []) {
                Cache::forget($this->key($owner));
            } else {
                Cache::put($this->key($owner), $items, now()->addDays(config('cart.ttl_days')));
            }

            return $items;
        });
    }

    public function forget(string $owner): void
    {
        Cache::forget($this->key($owner));
    }

    private function key(string $owner): string
    {
        return "cart:$owner";
    }
}
