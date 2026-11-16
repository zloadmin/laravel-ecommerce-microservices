<?php

return [

    // Lifetimes in seconds.
    'access_ttl' => (int) env('ACCESS_TOKEN_TTL', 15 * 60),
    'refresh_ttl' => (int) env('REFRESH_TOKEN_TTL', 30 * 24 * 60 * 60),

];
