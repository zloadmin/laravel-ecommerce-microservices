<?php

use App\Handlers\CancelRejectedOrder;
use App\Handlers\ConfirmOrder;

return [

    // Publishes: order.placed, order.confirmed, order.cancelled.
    'enabled' => true,

    'handlers' => [
        'stock.reserved' => ConfirmOrder::class,
        'stock.rejected' => CancelRejectedOrder::class,
    ],

];
