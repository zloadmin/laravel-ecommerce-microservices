<?php

return [

    /*
    | Access tokens are RS256 JWTs. Only the auth service holds the private key;
    | every other service verifies with the public key and never calls auth.
    */
    'jwt' => [
        'issuer' => env('JWT_ISSUER', 'ecommerce-auth'),
        'public_key_path' => env('JWT_PUBLIC_KEY_PATH', base_path('../../infra/keys/jwt-public.pem')),
        'private_key_path' => env('JWT_PRIVATE_KEY_PATH', base_path('../../infra/keys/jwt-private.pem')),
    ],

];
