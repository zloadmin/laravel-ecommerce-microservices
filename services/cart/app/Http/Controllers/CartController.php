<?php

namespace App\Http\Controllers;

use App\Services\CartStore;
use App\Services\CatalogClient;
use Ecommerce\Shared\Auth\AuthenticatedUser;
use Ecommerce\Shared\Http\ApiError;
use Ecommerce\Shared\Http\Middleware\CorrelationId;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CartController extends Controller
{
    public function __construct(
        private readonly CartStore $carts,
        private readonly CatalogClient $catalog,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return $this->present($this->carts->get($this->owner($request)));
    }

    /** Add a product, or increase its quantity if it is already in the cart. */
    public function add(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'uuid'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:'.config('cart.max_quantity')],
        ]);

        $this->assertPurchasable($data['product_id'], 'product_id');

        return $this->present($this->carts->update($this->owner($request), function (array $items) use ($data): array {
            $items[$data['product_id']] = min(
                ($items[$data['product_id']] ?? 0) + ($data['quantity'] ?? 1),
                config('cart.max_quantity'),
            );

            return $this->withinLineLimit($items);
        }));
    }

    /** Set the exact quantity of a product. */
    public function set(Request $request, string $product): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.config('cart.max_quantity')],
        ]);

        $this->assertPurchasable($product, 'product_id');

        return $this->present($this->carts->update($this->owner($request), function (array $items) use ($product, $data): array {
            $items[$product] = $data['quantity'];

            return $this->withinLineLimit($items);
        }));
    }

    public function remove(Request $request, string $product): JsonResponse
    {
        return $this->present($this->carts->update($this->owner($request), function (array $items) use ($product): array {
            unset($items[$product]);

            return $items;
        }));
    }

    public function clear(Request $request): JsonResponse
    {
        $this->carts->forget($this->owner($request));

        return $this->present([]);
    }

    /** After login: move a guest cart into the customer's cart. */
    public function merge(Request $request): JsonResponse
    {
        $data = $request->validate(['guest_cart_id' => ['required', 'uuid']]);

        $guest = 'guest:'.Str::lower($data['guest_cart_id']);
        $guestItems = $this->carts->get($guest);

        $merged = $this->carts->update($this->owner($request), function (array $items) use ($guestItems): array {
            foreach ($guestItems as $productId => $quantity) {
                // Lines beyond the limit are dropped rather than failing the login flow.
                if (isset($items[$productId]) || count($items) < config('cart.max_lines')) {
                    $items[$productId] = min(($items[$productId] ?? 0) + $quantity, config('cart.max_quantity'));
                }
            }

            return $items;
        });

        $this->carts->forget($guest);

        return $this->present($merged);
    }

    /** Turn the cart into an order. The orders service prices it and reserves stock. */
    public function checkout(Request $request): JsonResponse
    {
        $owner = $this->owner($request);
        $items = $this->carts->get($owner);

        if ($items === []) {
            return ApiError::response('cart_empty', 'The cart is empty.', 422);
        }

        try {
            $response = Http::baseUrl(config('cart.orders_url'))
                ->acceptJson()
                ->connectTimeout(2)
                ->timeout(10)
                // The customer's own token: orders authenticates them itself.
                ->withToken($request->bearerToken())
                ->withHeaders(array_filter([
                    CorrelationId::HEADER => Context::get('correlation_id'),
                    'Idempotency-Key' => $request->header('Idempotency-Key'),
                ]))
                ->post('/api/v1/orders', [
                    'items' => collect($items)
                        ->map(fn (int $quantity, string $productId) => ['product_id' => $productId, 'quantity' => $quantity])
                        ->values()->all(),
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Orders unavailable during checkout.', ['error' => $e->getMessage()]);

            return ApiError::response('service_unavailable', 'Checkout is unavailable. Your cart is unchanged.', 503);
        }

        if ($response->serverError()) {
            return ApiError::response('service_unavailable', 'Checkout is unavailable. Your cart is unchanged.', 503);
        }

        // The cart is emptied only once the order exists; on a rejection
        // (for example an unavailable product) it is kept so it can be fixed.
        if ($response->successful()) {
            $this->carts->forget($owner);
        }

        return response()->json($response->json(), $response->status());
    }

    /**
     * A cart belongs to the logged-in customer, or to a guest identified by a
     * client-generated UUID in X-Cart-Id (which is unguessable, so it acts as the key).
     */
    private function owner(Request $request): string
    {
        $user = $request->user();

        if ($user instanceof AuthenticatedUser) {
            return "user:{$user->id}";
        }

        $cartId = $request->header('X-Cart-Id');

        if (! is_string($cartId) || ! Str::isUuid($cartId)) {
            throw new BadRequestHttpException('Send an access token, or an X-Cart-Id header containing a UUID.');
        }

        return 'guest:'.Str::lower($cartId);
    }

    private function assertPurchasable(string $productId, string $field): void
    {
        if (! Str::isUuid($productId) || ! isset($this->catalog->products([$productId])[$productId])) {
            throw ValidationException::withMessages([$field => ['This product is not available.']]);
        }
    }

    /**
     * @param  array<string, int>  $items
     * @return array<string, int>
     */
    private function withinLineLimit(array $items): array
    {
        if (count($items) > config('cart.max_lines')) {
            throw ValidationException::withMessages(['product_id' => ['The cart cannot hold more than '.config('cart.max_lines').' different products.']]);
        }

        return $items;
    }

    /**
     * The cart with live catalog data. Prices are never stored in the cart,
     * so what is shown is always the current price.
     *
     * @param  array<string, int>  $items
     */
    private function present(array $items): JsonResponse
    {
        $products = $this->catalog->products(array_keys($items));
        $total = 0;
        $currency = null;
        $lines = [];

        foreach ($items as $productId => $quantity) {
            $product = $products[$productId] ?? null;

            if ($product === null) {
                // Deleted or deactivated since it was added.
                $lines[] = ['product_id' => $productId, 'quantity' => $quantity, 'available' => false];

                continue;
            }

            $lineTotal = $product['price']['amount'] * $quantity;
            $total += $lineTotal;
            $currency ??= $product['price']['currency'];

            $lines[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'available' => true,
                'in_stock' => ($product['stock'] ?? 0) >= $quantity,
                'name' => $product['name'],
                'slug' => $product['slug'] ?? null,
                'sku' => $product['sku'],
                'unit_price' => $product['price'],
                'line_total' => ['amount' => $lineTotal, 'currency' => $product['price']['currency']],
            ];
        }

        return response()->json(['data' => [
            'items' => $lines,
            'item_count' => array_sum($items),
            // Unavailable lines are excluded from the total.
            'total' => $currency === null ? null : ['amount' => $total, 'currency' => $currency],
        ]]);
    }
}
