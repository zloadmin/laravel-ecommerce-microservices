<?php

return [

    /*
    | Upstream services, reachable only on the internal Docker network.
    */
    'services' => [
        'auth' => env('AUTH_SERVICE_URL', 'http://auth:8080'),
        'catalog' => env('CATALOG_SERVICE_URL', 'http://catalog:8080'),
        'orders' => env('ORDERS_SERVICE_URL', 'http://orders:8080'),
        'cart' => env('CART_SERVICE_URL', 'http://cart:8080'),
    ],

    /*
    | Public resource prefix => upstream service.
    | /api/v1/{resource}/... is forwarded to the same path on that service.
    */
    'routes' => [
        'auth' => 'auth',
        'users' => 'auth',
        'products' => 'catalog',
        'categories' => 'catalog',
        'orders' => 'orders',
        'cart' => 'cart',
    ],

    'connect_timeout' => (float) env('GATEWAY_CONNECT_TIMEOUT', 2),
    'timeout' => (float) env('GATEWAY_TIMEOUT', 10),

];
