<?php

/**
 * End-to-end checkout test against a running stack, through the gateway.
 * Needs the seeded admin account (make seed). Run with: make e2e
 *
 * It creates its own product and customer, and deletes the product afterwards.
 */
$base = rtrim(getenv('E2E_BASE_URL') ?: 'http://gateway:8080', '/').'/api/v1';
$run = bin2hex(random_bytes(4));
$failures = 0;

function call(string $method, string $url, ?array $body = null, array $headers = []): array
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [...$headers, 'Accept: application/json', 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $body === null ? null : json_encode($body),
    ]);
    $raw = curl_exec($curl);

    if ($raw === false) {
        fwrite(STDERR, "Request failed: $method $url: ".curl_error($curl)."\n");
        exit(2);
    }

    return [curl_getinfo($curl, CURLINFO_RESPONSE_CODE), json_decode($raw, true) ?? []];
}

function check(string $what, mixed $expected, mixed $actual): void
{
    global $failures;
    $ok = $expected === $actual;
    $failures += $ok ? 0 : 1;
    echo ($ok ? '  ok    ' : '  FAIL  ').$what.($ok ? '' : ': expected '.json_encode($expected).', got '.json_encode($actual))."\n";
}

/** Poll until the order leaves "pending" (the saga is asynchronous). */
function settledOrder(string $url, array $auth): array
{
    for ($i = 0; $i < 60; $i++) {
        [, $order] = call('GET', $url, null, $auth);
        if (($order['data']['status'] ?? 'pending') !== 'pending') {
            break;
        }
        usleep(500_000);
    }

    return $order['data'] ?? [];
}

$stock = fn (string $id, array $auth) => call('GET', "$base/products/$id", null, $auth)[1]['data']['stock'] ?? null;

echo "Setup\n";
[$status, $login] = call('POST', "$base/auth/login", ['email' => 'admin@example.com', 'password' => 'password']);
check('admin can log in (run "make seed" if this fails)', 200, $status);
$admin = ['Authorization: Bearer '.($login['data']['access_token'] ?? '')];

[$status, $product] = call('POST', "$base/products", ['name' => "E2E Widget $run", 'sku' => "E2E-$run", 'price_amount' => 1250, 'stock' => 3], $admin);
check('admin creates a product with 3 in stock', 201, $status);
$productId = $product['data']['id'] ?? '';

[$status, $registered] = call('POST', "$base/auth/register", ['name' => 'E2E Customer', 'email' => "e2e-$run@example.com", 'password' => "pw-$run-long"]);
check('customer registers', 201, $status);
$customer = ['Authorization: Bearer '.($registered['data']['access_token'] ?? '')];

echo "Access control\n";
check('checkout without a token is rejected', 401, call('POST', "$base/orders", ['items' => []])[0]);
check('customer cannot create products', 403, call('POST', "$base/products", ['name' => 'x', 'sku' => "X-$run", 'price_amount' => 1], $customer)[0]);

echo "Successful order\n";
$items = ['items' => [['product_id' => $productId, 'quantity' => 2]]];
[$status, $placed] = call('POST', "$base/orders", $items, [...$customer, "Idempotency-Key: e2e-$run-first"]);
check('order is accepted', 201, $status);
check('total is priced by the catalog', ['amount' => 2500, 'currency' => 'USD'], $placed['data']['total'] ?? null);
$firstId = $placed['data']['id'] ?? '';

[$status, $replayed] = call('POST', "$base/orders", $items, [...$customer, "Idempotency-Key: e2e-$run-first"]);
check('replaying the idempotency key returns the same order', [200, $firstId], [$status, $replayed['data']['id'] ?? null]);

check('order becomes confirmed', 'confirmed', settledOrder("$base/orders/$firstId", $customer)['status'] ?? null);
check('stock is reduced to 1', 1, $stock($productId, $admin));

echo "Out of stock\n";
[, $placed] = call('POST', "$base/orders", $items, $customer);
$second = settledOrder("$base/orders/".($placed['data']['id'] ?? ''), $customer);
check('second order for 2 is cancelled', ['cancelled', 'insufficient_stock'], [$second['status'] ?? null, $second['cancellation_reason'] ?? null]);
check('stock is untouched', 1, $stock($productId, $admin));

echo "Cancellation\n";
[$status, $cancelled] = call('POST', "$base/orders/$firstId/cancel", null, $customer);
check('customer cancels the confirmed order', [200, 'cancelled'], [$status, $cancelled['data']['status'] ?? null]);
check('cancelling twice is a conflict', 409, call('POST', "$base/orders/$firstId/cancel", null, $customer)[0]);

$restored = null;
for ($i = 0; $i < 60 && $restored !== 3; $i++) {
    usleep(500_000);
    $restored = $stock($productId, $admin);
}
check('stock is restored to 3', 3, $restored);
check('customer sees both orders', 2, count(call('GET', "$base/orders", null, $customer)[1]['data'] ?? []));

echo "Cleanup\n";
check('admin deletes the product', 204, call('DELETE', "$base/products/$productId", null, $admin)[0]);

echo $failures === 0 ? "\nAll checks passed.\n" : "\n$failures check(s) failed.\n";
exit($failures === 0 ? 0 : 1);
