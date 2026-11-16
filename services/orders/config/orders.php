<?php

return [

    'catalog_url' => env('CATALOG_SERVICE_URL', 'http://catalog:8080'),

    // A pending order is cancelled when the catalog has not answered within this time.
    'pending_timeout_minutes' => (int) env('ORDER_PENDING_TIMEOUT_MINUTES', 15),

    'max_lines' => 50,
    'max_quantity' => 100,

];
