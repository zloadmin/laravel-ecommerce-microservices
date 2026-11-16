<?php

return [

    'catalog_url' => env('CATALOG_SERVICE_URL', 'http://catalog:8080'),
    'orders_url' => env('ORDERS_SERVICE_URL', 'http://orders:8080'),

    // A cart is deleted this long after it was last changed.
    'ttl_days' => (int) env('CART_TTL_DAYS', 30),

    // Same limits as an order, so a full cart can always be checked out.
    'max_lines' => 50,
    'max_quantity' => 100,

];
