<?php

return [
    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'environment' => env('MIDTRANS_ENVIRONMENT', 'sandbox'),
    'is_production' => env('MIDTRANS_ENVIRONMENT') === 'production',
];
