<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | The gateway used when none is explicitly specified. Must match a key
    | under "gateways" below.
    |
    */
    'default' => env('PAYMENT_GATEWAY', 'braintree'),

    /*
    |--------------------------------------------------------------------------
    | Gateways
    |--------------------------------------------------------------------------
    |
    | Each gateway maps to a driver class implementing
    | Payments\Contracts\PaymentGateway. Add a new entry here
    | (and a matching driver class + manager binding) to support another
    | payment provider without touching any calling code.
    |
    */
    'gateways' => [

        'braintree' => [
            'driver' => 'braintree',
            'environment' => env('BRAINTREE_ENVIRONMENT', 'sandbox'), // sandbox | production
            'merchant_id' => env('BRAINTREE_MERCHANT_ID'),
            'public_key' => env('BRAINTREE_PUBLIC_KEY'),
            'private_key' => env('BRAINTREE_PRIVATE_KEY'),
        ],

        // Example shape for a future gateway:
        // 'stripe' => [
        //     'driver' => 'stripe',
        //     'secret_key' => env('STRIPE_SECRET_KEY'),
        //     'public_key' => env('STRIPE_PUBLIC_KEY'),
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Default ISO 4217 currency code used when a charge/refund request
    | does not specify one.
    |
    */
    'currency' => env('PAYMENT_CURRENCY', 'USD'),
];
