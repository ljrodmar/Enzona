<?php

return [
    'api_hosts' => [
        'sandbox' => 'https://apisandbox.enzona.net',
        'production' => 'https://api.enzona.net',
    ],
    'oauth_path' => '/token',
    'payment_path' => '/payment/v1.0.0/payments',
    'oauth_scope' => 'enzona_business_payment enzona_business_qr',
    'timeout' => 30,
    'token_expiration_buffer' => 30,
    'register_routes' => false,
    'route_middleware' => ['web'],
];
