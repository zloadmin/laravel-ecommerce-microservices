<?php

use App\Handlers\ReleaseStock;
use App\Handlers\ReserveStock;

return [

    // Publishes: product.created, product.updated, product.deleted, stock.reserved, stock.rejected.
    'enabled' => true,

    'handlers' => [
        'order.placed' => ReserveStock::class,
        'order.cancelled' => ReleaseStock::class,
    ],

];
